<?php

namespace App\Http\Controllers;

use App\Enums\TypeLogEnum;
use App\Http\Actions\CreateLogAction;
use App\Http\Actions\GrantPermissionAction;
use App\Http\Actions\SyncUserRoleAction;
use App\Http\Requests\UserPermissionStoreRequest;
use App\Http\Requests\UserRoleUpdateRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * UC01 / RF01 — o administrador gerencia as autorizações de cada usuário.
 */
class UserPermissionController extends Controller
{
    /**
     * O que este usuário pode, e por quê.
     *
     * Separa o que vem do perfil do que foi concedido individualmente: sem
     * essa distinção, a administração não tem como saber o que some se o
     * perfil da pessoa mudar.
     */
    public function index(int $id)
    {
        $user = $this->findUser($id);

        if (! $user instanceof User) {
            return $user;
        }

        $doPerfil = $user->getPermissionsViaRoles()->pluck('name')->sort()->values();
        $individuais = $user->getDirectPermissions()->pluck('name')->sort()->values();

        return MessageService::success("Autorizações do usuário {$user->id}.", [
            'user_id' => $user->id,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'from_role' => $doPerfil,
            'direct' => $individuais,
            'effective' => $doPerfil->merge($individuais)->unique()->sort()->values(),
        ]);
    }

    public function store(UserPermissionStoreRequest $request, int $id)
    {
        $user = $this->findUser($id);

        if (! $user instanceof User) {
            return $user;
        }

        try {
            DB::beginTransaction();

            $user = GrantPermissionAction::execute($user, $request->validated()['permission']);

            DB::commit();

            return MessageService::success('Caso de uso concedido.', [
                'user_id' => $user->id,
                'direct' => $user->getDirectPermissions()->pluck('name')->sort()->values(),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    public function destroy(int $id, string $permission)
    {
        $user = $this->findUser($id);

        if (! $user instanceof User) {
            return $user;
        }

        try {
            DB::beginTransaction();

            $user = GrantPermissionAction::revoke($user, SanitizeUtil::sanitizeString($permission));

            DB::commit();

            return MessageService::success('Caso de uso revogado.', [
                'user_id' => $user->id,
                'direct' => $user->getDirectPermissions()->pluck('name')->sort()->values(),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Troca o perfil, que é a concessão em bloco.
     */
    public function updateRole(UserRoleUpdateRequest $request, int $id)
    {
        $user = $this->findUser($id);

        if (! $user instanceof User) {
            return $user;
        }

        try {
            DB::beginTransaction();

            $anterior = $user->role->value;
            $user->update(['role' => $request->validated()['role']]);
            SyncUserRoleAction::execute($user);

            $description = "Perfil do usuário {$user->id} {$user->name} alterado de {$anterior} para {$user->role->value}.";
            CreateLogAction::execute(TypeLogEnum::USER->value, $description);

            DB::commit();

            return MessageService::success($description, $user->fresh());
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Devolve o usuário ou a resposta de 404 pronta, para que os métodos não
     * repitam a busca nem abram transação antes de saber que ele existe.
     */
    private function findUser(int $id): User|JsonResponse
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $user = User::find($id);

        if (! $user) {
            return MessageService::error("Usuário {$id} não encontrado.", 404);
        }

        return $user;
    }
}
