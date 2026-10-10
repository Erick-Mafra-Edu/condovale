<?php

namespace App\Http\Controllers;

use App\Http\Actions\ChangeOwnPasswordAction;
use App\Http\Actions\UpdateOwnProfileAction;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UserProfileUpdateRequest;
use App\Http\Utils\AuthUtil;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * O próprio cadastro de quem está autenticado.
 *
 * Separado do UserController de propósito: lá a administração mexe em qualquer
 * pessoa, aqui cada um mexe apenas em si. O id nunca vem da requisição — vem da
 * sessão —, então não há como editar o cadastro alheio por esta porta.
 */
class ProfileController extends Controller
{
    public function show()
    {
        return MessageService::success('Cadastro retornado.', AuthUtil::user());
    }

    public function update(UserProfileUpdateRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = UpdateOwnProfileAction::execute(AuthUtil::user(), $request->validated());

            DB::commit();

            return MessageService::success('Cadastro atualizado.', $user);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Troca da própria senha — é por aqui que o primeiro acesso se desbloqueia.
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $item = (object) $request->validated();

        try {
            DB::beginTransaction();

            $user = ChangeOwnPasswordAction::execute(
                $request,
                AuthUtil::user(),
                $item->current_password,
                $item->password,
            );

            DB::commit();

            return MessageService::success('Senha alterada.', $user);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
