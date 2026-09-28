<?php

namespace App\Http\Middleware;

use App\Http\Utils\AuthUtil;
use App\Services\MessageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda das rotas que só fazem sentido para quem ocupa uma unidade.
 *
 * A pergunta que este middleware responde é "esta pessoa está associada a uma
 * unidade ou ao condomínio?". Morador responde por uma unidade e precisa do
 * vínculo; síndico, administrador e funcionário respondem pelo condomínio
 * inteiro e passam sem vínculo nenhum — exigir unidade deles travaria a
 * administração do sistema.
 *
 * Reservar área comum ou abrir ocorrência sem unidade vinculada produziria
 * registro órfão: não haveria a que unidade cobrar a reserva nem de onde
 * partiu a ocorrência. Por isso a checagem é de acesso, e não de validação de
 * formulário.
 */
class EnsureUserBelongsToUnit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AuthUtil::user();

        if (! $user) {
            return MessageService::error('É necessário estar autenticado para continuar.', 401);
        }

        if ($user->requiresUnitLink() && ! $user->belongsToUnit()) {
            return MessageService::error(
                'Seu usuário ainda não está vinculado a uma unidade. Procure a administração do condomínio.',
                403
            );
        }

        return $next($request);
    }
}
