<?php

namespace App\Http\Controllers;

use App\Http\Actions\CreateUserAction;
use App\Http\Actions\DeactivateUserAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\UpdateUserAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * RF01 / UC01 — a administração gerencia os usuários do condomínio.
 */
class UserController extends Controller
{
    public function index(DefaultPaginationRequest $request, User $user)
    {
        $item = (object) $request->validated();
        $searchColumns = ['name', 'email'];

        $response = HandlePaginationAction::execute($request, $user, $searchColumns);
        $users = $response->paginate($item->limit ?? 10);

        return MessageService::success('Usuários retornados.', $users, true);
    }

    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $user = User::find($id);

        if (! $user) {
            return MessageService::error("Usuário {$id} não encontrado.", 404);
        }

        return MessageService::success("Usuário {$user->id} encontrado.", $user);
    }

    /**
     * A conta nasce com senha temporária marcada para troca.
     *
     * A senha volta no corpo da resposta: é por ela que quem cadastrou repassa
     * o primeiro acesso à pessoa, enquanto não há envio de e-mail configurado.
     * Não é senha definitiva de ninguém — o `password.change` obriga a troca no
     * primeiro uso, e a temporária deixa de valer ali.
     */
    public function store(UserStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $resultado = CreateUserAction::execute($request->validated());

            DB::commit();

            return MessageService::success('Usuário cadastrado.', [
                'user' => $resultado['user'],
                'temporary_password' => $resultado['temporary_password'],
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Edita qualquer dado de qualquer usuário, inclusive perfil e situação.
     *
     * A reativação é por aqui, mandando `status: active` — não existe endpoint
     * próprio para ela.
     */
    public function update(UserUpdateRequest $request, int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $user = User::find($id);

        if (! $user) {
            return MessageService::error("Usuário {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            $user = UpdateUserAction::execute($user, $request->validated());

            DB::commit();

            return MessageService::success("Usuário {$user->id} editado.", $user);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * RN04 — inativa sem apagar. A sessão de quem estiver dentro cai na
     * requisição seguinte, pelo middleware user.active.
     */
    public function deactivate(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $user = User::find($id);

        if (! $user) {
            return MessageService::error("Usuário {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            $user = DeactivateUserAction::execute($user);

            DB::commit();

            return MessageService::success("Usuário {$user->id} inativado.", $user);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
