<?php

namespace App\Http\Controllers;

use App\Http\Actions\AuthenticateUserAction;
use App\Http\Requests\LoginRequest;
use App\Http\Utils\AuthUtil;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * UC02 / RF02 — opens the session for valid credentials of an active user.
     */
    public function login(LoginRequest $request)
    {
        $item = (object) $request->validated();

        try {
            DB::beginTransaction();

            $user = AuthenticateUserAction::execute($request, $item->email, $item->password);

            DB::commit();

            return MessageService::success('Autenticado com sucesso.', ['user' => $user]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Who is on the other side of the session cookie.
     *
     * A rota é pública porque a interface a consulta ao abrir, antes de saber
     * se há sessão. Visitante não é erro: a resposta sai sem `data`, e é assim
     * que o cliente distingue "ninguém autenticado" de uma falha.
     */
    public function session()
    {
        $user = AuthUtil::user();

        if (! $user) {
            return MessageService::success('Nenhuma sessão ativa.');
        }

        return MessageService::success('Sessão ativa.', ['user' => $user]);
    }

    /**
     * Ends the session. Idempotente: sair sem estar dentro não é erro.
     */
    public function logout(Request $request)
    {
        try {
            DB::beginTransaction();

            AuthenticateUserAction::logout($request);

            DB::commit();

            return MessageService::success('Sessão encerrada.');
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
