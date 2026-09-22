<?php

namespace App\Http\Middleware;

use App\Http\Utils\AuthUtil;
use App\Services\MessageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RN04 — only registered and active users reach the features.
 *
 * Checking the status at login is not enough: a session opened before the
 * deactivation would keep working until it expired.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AuthUtil::user();

        if ($user && ! $user->isActive()) {
            AuthUtil::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return MessageService::error('Usuário inativo não pode acessar o sistema.', 403);
        }

        return $next($request);
    }
}
