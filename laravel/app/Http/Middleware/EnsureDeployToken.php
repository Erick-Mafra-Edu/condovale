<?php

namespace App\Http\Middleware;

use App\Services\MessageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeployToken
{
    /**
     * Guards the deployment routes with a shared secret.
     *
     * While the routine is disabled or badly configured the route answers 404
     * instead of 403, so it does not announce its own existence to whoever is
     * sweeping the domain for endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('deploy.token');

        if (! config('deploy.enabled') || strlen($token) < (int) config('deploy.token_min_length')) {
            return MessageService::error('Recurso não encontrado.', 404);
        }

        $sent = (string) ($request->header('X-Deploy-Token') ?? $request->input('token', ''));

        // hash_equals compara em tempo constante: um == comum devolve mais
        // rápido quanto menor o prefixo correto, e isso permite descobrir o
        // token caractere a caractere.
        if ($sent === '' || ! hash_equals($token, $sent)) {
            return MessageService::error('Token de implantação inválido.', 403);
        }

        return $next($request);
    }
}
