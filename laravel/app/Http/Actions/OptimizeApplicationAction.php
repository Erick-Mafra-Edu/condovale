<?php

namespace App\Http\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * UC-T01 / RNF01 — gera os caches de inicialização no servidor.
 *
 * A medição contra produção mostrou de onde vem o tempo de resposta: servir um
 * arquivo estático da hospedagem já custa cerca de 660 ms, e a inicialização
 * do framework acrescenta uns 270 ms. O trabalho das listagens — consulta,
 * paginação, serialização — responde por menos de 50 ms. Ou seja, otimizar a
 * consulta não move o ponteiro; o que move é não reler quinze arquivos de
 * configuração e remontar a tabela de rotas a cada requisição.
 *
 * Isto normalmente seria `php artisan optimize` na implantação, mas o plano
 * gratuito não tem SSH: a única forma de rodar artisan no servidor é por HTTP,
 * e é o que esta Action existe para permitir.
 *
 * O cache de configuração é derivado do `.env`. Se o `.env` ou um arquivo de
 * config mudar e o cache ficar, a aplicação passa a rodar com a configuração
 * velha sem avisar — por isso o publicador apaga estes arquivos a cada envio,
 * e a aplicação volta a rodar sem cache (mais lenta, nunca errada) até alguém
 * chamar esta rota de novo.
 */
class OptimizeApplicationAction
{
    /**
     * @return array<string, string>
     */
    public static function execute(): array
    {
        return self::rodar(['config:cache', 'route:cache']);
    }

    /**
     * @return array<string, string>
     */
    public static function clear(): array
    {
        return self::rodar(['config:clear', 'route:clear']);
    }

    /**
     * @param  list<string>  $comandos
     * @return array<string, string>
     */
    private static function rodar(array $comandos): array
    {
        $saidas = [];

        foreach ($comandos as $comando) {
            Artisan::call($comando);

            $saidas[$comando] = trim(Artisan::output()) ?: 'ok';
        }

        return $saidas;
    }
}
