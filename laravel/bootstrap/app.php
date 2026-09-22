<?php

use App\Http\Middleware\CheckUseCase;
use App\Http\Middleware\EnsureDeployToken;
use App\Http\Middleware\EnsureUserIsActive;
use App\Services\MessageService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // A autenticação da API é por sessão em cookie HttpOnly (nenhum token
        // trafega para o cliente), então o grupo "api" precisa dos middlewares
        // de cookie e de sessão.
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);

        $middleware->alias([
            'user.active' => EnsureUserIsActive::class,
            'use.case' => CheckUseCase::class,
            'deploy.token' => EnsureDeployToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Todo endpoint responde pelo MessageService, inclusive quando quem
        // encerra a requisição é uma exceção.
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(static function (Throwable $th, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($th instanceof AuthenticationException) {
                return MessageService::error('É necessário estar autenticado para continuar.', 401);
            }

            if ($th instanceof NotFoundHttpException) {
                return MessageService::error('Recurso não encontrado.', 404);
            }

            return null;
        });
    })->create();
