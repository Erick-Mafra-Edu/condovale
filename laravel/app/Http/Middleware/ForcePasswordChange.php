<?php

namespace App\Http\Middleware;

use App\Http\Utils\AuthUtil;
use App\Services\MessageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prende a sessão na troca de senha enquanto a conta usar a senha temporária.
 *
 * A senha temporária viaja por e-mail e pode ter passado por caixas de entrada,
 * encaminhamentos e registros de servidor. Deixá-la funcionando como senha
 * comum transformaria um e-mail antigo em acesso permanente à conta.
 *
 * Por isso a sessão abre — a pessoa precisa estar autenticada para trocar a
 * senha — mas não alcança nada além da rota de troca. O código
 * `PASSWORD_CHANGE_REQUIRED` existe para o cliente levar direto à tela certa,
 * em vez de mostrar um "sem permissão" que não explica nada.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AuthUtil::user();

        if ($user && $user->must_change_password) {
            return MessageService::error(
                'Defina uma nova senha antes de continuar.',
                403,
                'PASSWORD_CHANGE_REQUIRED'
            );
        }

        return $next($request);
    }
}
