<?php

namespace App\Http\Actions;

/**
 * Relata se o servidor consegue enviar e-mail, e por onde.
 *
 * A hospedagem compartilhada restringe a função mail() do PHP e bloqueia a
 * maior parte das portas de saída, mas a documentação dela diz liberar SMTP
 * externo na 587. Isso é verificável de dentro do próprio servidor, e verificar
 * vale mais que confiar no que está escrito: a resposta decide se o envio da
 * senha temporária tem como existir.
 */
class CheckOutboundMailAction
{
    /**
     * Portas testadas. A 587 é a que a documentação do host diz liberar; as
     * outras entram para a comparação não ficar sem contraste.
     */
    private const PORTAS = [587, 465, 25, 2525];

    /**
     * Servidor usado só para abrir a conexão. Nenhuma mensagem é enviada.
     */
    private const ALVO = 'smtp.gmail.com';

    private const TIMEOUT_SEGUNDOS = 8;

    public static function execute(): array
    {
        $portas = [];

        foreach (self::PORTAS as $porta) {
            $portas[$porta] = self::testarPorta($porta);
        }

        return [
            'mail_function_existe' => function_exists('mail'),
            'funcoes_desabilitadas' => ini_get('disable_functions') ?: '(nenhuma)',
            'transporte_configurado' => config('mail.default'),
            'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
            'openssl' => extension_loaded('openssl'),
            'saida_por_porta' => $portas,
        ];
    }

    /**
     * Abre um socket e lê o banner, sem autenticar nem mandar mensagem: o que
     * interessa é saber se a conexão de saída é permitida.
     */
    private static function testarPorta(int $porta): array
    {
        $inicio = microtime(true);
        $erro = null;
        $codigo = 0;

        $socket = @fsockopen(self::ALVO, $porta, $codigo, $erro, self::TIMEOUT_SEGUNDOS);
        $ms = (int) round((microtime(true) - $inicio) * 1000);

        if (! $socket) {
            return ['aberta' => false, 'erro' => $erro ?: "codigo {$codigo}", 'ms' => $ms];
        }

        stream_set_timeout($socket, self::TIMEOUT_SEGUNDOS);
        $banner = trim((string) fgets($socket, 256));
        fclose($socket);

        return ['aberta' => true, 'banner' => mb_substr($banner, 0, 60), 'ms' => $ms];
    }
}
