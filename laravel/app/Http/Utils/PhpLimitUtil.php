<?php

namespace App\Http\Utils;

/**
 * Lê os limites do php.ini do host em números com que dá para fazer conta.
 *
 * Existe porque o limite que vale é o do servidor, não o da configuração da
 * aplicação. No InfinityFree ele fica no php.ini mestre e não pode ser
 * alterado; aqui na máquina de desenvolvimento costuma ser 2 MB. Se a
 * aplicação anunciasse 5 MB num host de 2 MB, o PHP descartaria o arquivo
 * antes de a validação vê-lo, e a mensagem seria "o campo é obrigatório" —
 * para quem acabou de escolher a foto.
 */
class PhpLimitUtil
{
    /**
     * Converte a notação abreviada do php.ini ("2M", "128M", "1G") em bytes.
     *
     * Devolve null para "-1" e para valor vazio: sem limite, ou não informado.
     */
    public static function bytes(string|false|null $valor): ?int
    {
        $valor = trim((string) $valor);

        if ($valor === '' || $valor === '-1') {
            return null;
        }

        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    /**
     * O maior arquivo, em KB, que de fato chega à aplicação.
     *
     * É o menor entre o configurado e os dois limites do PHP: upload_max_filesize
     * vale por arquivo, post_max_size vale pela requisição inteira — e o
     * arquivo viaja dentro dela.
     */
    public static function uploadMaxKilobytes(int $configurado, ?string $uploadMax = null, ?string $postMax = null): int
    {
        $limites = array_filter([
            $configurado * 1024,
            self::bytes($uploadMax ?? ini_get('upload_max_filesize')),
            self::bytes($postMax ?? ini_get('post_max_size')),
        ], fn ($limite) => $limite !== null && $limite > 0);

        return intdiv(min($limites), 1024);
    }

    /**
     * Quanta memória o processo ainda pode alocar, em bytes. Null = sem limite.
     */
    public static function availableMemory(): ?int
    {
        $limite = self::bytes(ini_get('memory_limit'));

        return $limite === null ? null : $limite - memory_get_usage(true);
    }
}
