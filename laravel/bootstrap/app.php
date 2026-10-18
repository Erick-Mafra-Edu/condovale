<?php

use App\Http\Middleware\CheckUseCase;
use App\Http\Middleware\EnsureDeployToken;
use App\Http\Middleware\EnsureUserBelongsToUnit;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Utils\PhpLimitUtil;
use App\Services\MessageService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
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
            'unit.linked' => EnsureUserBelongsToUnit::class,
            'password.change' => ForcePasswordChange::class,
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

            // Passou do post_max_size do host: o PHP descarta o corpo inteiro
            // antes de a aplicação vê-lo, e sem isto a resposta seria uma
            // mensagem em inglês sem dizer qual é o limite.
            if ($th instanceof PostTooLargeException) {
                $limite = round(PhpLimitUtil::uploadMaxKilobytes(
                    (int) config('uploads.occurrence_attachments.max_kilobytes')
                ) / 1024, 1);

                return MessageService::error(
                    "O envio passou do limite de {$limite} MB aceito pelo servidor.",
                    413
                );
            }

            return null;
        });
    })->create();
