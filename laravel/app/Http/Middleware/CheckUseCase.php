<?php

namespace App\Http\Middleware;

use App\Enums\UseCase;
use App\Http\Utils\AuthUtil;
use App\Services\MessageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorization of the route group, based on the use case diagram.
 *
 * The route declares which use cases reach it and the middleware lets the
 * request through when the role of the authenticated user holds any of them.
 * This is what keeps permission checks out of the controllers.
 */
class CheckUseCase
{
    public function handle(Request $request, Closure $next, string ...$useCases): Response
    {
        $user = AuthUtil::user();

        if (! $user) {
            return MessageService::error('É necessário estar autenticado para continuar.', 401);
        }

        foreach ($useCases as $name) {
            $useCase = UseCase::tryFrom($name);

            if ($useCase && $user->hasUseCase($useCase)) {
                return $next($request);
            }
        }

        return MessageService::error('Você não tem permissão para executar esta operação.', 403);
    }
}
