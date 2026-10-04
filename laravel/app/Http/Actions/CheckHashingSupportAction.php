<?php

namespace App\Http\Actions;

use Illuminate\Support\Facades\Hash;

/**
 * Relata se o PHP de quem está rodando atende à RNF02.
 *
 * Existe porque a hospedagem compartilhada não publica a configuração do PHP e
 * não há SSH para inspecioná-la: a única forma de saber se o Argon2id está
 * disponível lá é perguntar de dentro da própria aplicação. Suporte ao
 * algoritmo não basta — o Argon2id com os parâmetros do projeto reserva 64 MB
 * por hash, e um memory_limit menor derruba a geração da senha.
 */
class CheckHashingSupportAction
{
    /**
     * Senha descartável usada só para o teste de ida e volta.
     */
    private const SAMPLE = 'condovale-probe';

    public static function execute(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'algos' => password_algos(),
            'argon2id_disponivel' => defined('PASSWORD_ARGON2ID'),
            'argon2i_disponivel' => defined('PASSWORD_ARGON2I'),
            'driver_configurado' => config('hashing.driver'),
            'parametros_argon' => config('hashing.argon'),
            'argon2id' => self::roundTrip('argon2id'),
            'bcrypt' => self::roundTrip('bcrypt'),
            'custo_por_parametro' => self::benchmark(),
        ];
    }

    /**
     * Mede o custo de cada conjunto de parâmetros no hardware de quem roda.
     *
     * O custo do Argon2id é a sua defesa, mas ele é pago em toda autenticação:
     * escolher os números a partir de uma medição no servidor de verdade evita
     * tanto o excesso — que torna o login lento para o morador — quanto a
     * economia que enfraquece o hash. Os conjuntos abaixo são os recomendados
     * pela OWASP, mais o atualmente configurado.
     */
    private static function benchmark(): array
    {
        $candidates = [
            'm=64MiB t=4 p=1' => ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1],
            'm=46MiB t=1 p=1' => ['memory_cost' => 47104, 'time_cost' => 1, 'threads' => 1],
            'm=32MiB t=3 p=1' => ['memory_cost' => 32768, 'time_cost' => 3, 'threads' => 1],
            'm=19MiB t=2 p=1' => ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1],
        ];

        $results = [];

        foreach ($candidates as $label => $options) {
            $started = microtime(true);

            try {
                password_hash(self::SAMPLE, PASSWORD_ARGON2ID, $options);
                $results[$label] = (int) round((microtime(true) - $started) * 1000).' ms';
            } catch (\Throwable $th) {
                $results[$label] = 'falhou: '.$th->getMessage();
            }
        }

        return $results;
    }

    /**
     * Gera e confere um hash de verdade com o driver pedido.
     *
     * Só o teste completo responde à pergunta que importa: não adianta o
     * algoritmo existir se a geração estoura o limite de memória ou de tempo
     * do host. A falha é capturada e devolvida como texto para que o
     * diagnóstico chegue inteiro em vez de virar erro 500.
     */
    private static function roundTrip(string $driver): array
    {
        $started = microtime(true);

        try {
            $hash = Hash::driver($driver)->make(self::SAMPLE);

            return [
                'funciona' => Hash::driver($driver)->check(self::SAMPLE, $hash),
                'prefixo' => substr($hash, 0, 30),
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (\Throwable $th) {
            return [
                'funciona' => false,
                'erro' => $th->getMessage(),
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }
}
