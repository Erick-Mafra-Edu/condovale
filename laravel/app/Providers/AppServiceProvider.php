<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind the shared host proxy the request arrives as plain HTTP, so
        // every generated URL would point to http:// and break the session
        // cookie, which is marked secure in production.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // The limit is keyed by the account, never by the IP address: the
        // residents of a condominium reach the system through the same network,
        // so an IP based limit would lock everyone out when one person mistypes
        // a password.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)
                ->by('login:'.$key)
                ->response(fn (Request $request, array $headers) => response()->json([
                    'status' => false,
                    'error' => 'Muitas tentativas de login para esta conta. Aguarde um minuto e tente novamente.',
                    'code' => 'TOO_MANY_ATTEMPTS',
                ], 429, $headers));
        });

        // A implantação não tem conta para chavear: o banco ainda nem existe.
        // A chave é fixa, um balde único para o endpoint inteiro, que é o que
        // se pode fazer sem recorrer ao IP.
        RateLimiter::for('deploy', fn () => Limit::perMinute(3)
            ->by('deploy')
            ->response(fn (Request $request, array $headers) => response()->json([
                'status' => false,
                'error' => 'Muitas tentativas de implantação. Aguarde um minuto e tente novamente.',
                'code' => 'TOO_MANY_ATTEMPTS',
            ], 429, $headers)));
    }
}
