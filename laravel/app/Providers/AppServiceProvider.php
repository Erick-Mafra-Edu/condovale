<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

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
        // O MySQL do host compartilhado limita uma chave a 1000 bytes, e um
        // varchar(255) em utf8mb4 ocupa 1020 — qualquer coluna de texto sem
        // tamanho explícito que entre num índice derruba a migration com
        // "Specified key was too long". O limite vale para todos os ambientes,
        // e não só para produção: com tamanhos diferentes, um valor que cabe no
        // SQLite local estoura no servidor, e o erro só aparece no deploy.
        Schema::defaultStringLength(191);

        // Behind the shared host proxy the request arrives as plain HTTP, so
        // every generated URL would point to http:// and break the session
        // cookie, which is marked secure in production.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            self::guardPasswordHashing();
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

    /**
     * RNF02 — as senhas são armazenadas exclusivamente como Argon2id.
     *
     * Um driver trocado por engano não quebra nada visivelmente: a aplicação
     * continua respondendo e passa a gravar hash mais fraco, sem ninguém
     * perceber, até alguém auditar o banco. Em produção isso precisa ser falha
     * alta, no momento do deploy, e não um requisito violado em silêncio.
     */
    private static function guardPasswordHashing(): void
    {
        $driver = config('hashing.driver');

        if ($driver !== 'argon2id') {
            throw new RuntimeException(
                "RNF02 exige Argon2id para as senhas, mas HASH_DRIVER está como \"{$driver}\"."
            );
        }

        if (! defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException(
                'RNF02 exige Argon2id, que não está disponível neste PHP. '.
                'Consulte GET /api/deploy/hashing para o diagnóstico do servidor.'
            );
        }
    }
}
