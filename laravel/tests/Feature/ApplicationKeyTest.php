<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A APP_KEY assina o cookie de sessão e criptografa tudo que passa pelo Crypt.
 * Quem a tem forja a sessão de qualquer usuário sem saber a senha.
 */
class ApplicationKeyTest extends TestCase
{
    public function test_a_chave_nao_tem_valor_padrao_escrito_no_codigo(): void
    {
        // Guarda de regressão. O import deste projeto trazia uma chave real
        // como fallback do env(), versionada no repositório: qualquer ambiente
        // que subisse sem a variável assinaria sessões com uma chave pública,
        // e nada indicaria o problema porque o site continuaria funcionando.
        $fonte = file_get_contents(config_path('app.php'));

        $this->assertStringContainsString("'key' => env('APP_KEY')", $fonte);
        $this->assertDoesNotMatchRegularExpression(
            "/env\(\s*'APP_KEY'\s*,/",
            $fonte,
            'config/app.php voltou a ter um valor padrão para a APP_KEY.'
        );
    }

    public function test_nenhuma_chave_fica_versionada_no_repositorio(): void
    {
        $versionados = [
            config_path('app.php'),
            base_path('.env.example'),
        ];

        foreach ($versionados as $arquivo) {
            $conteudo = file_get_contents($arquivo);

            $this->assertDoesNotMatchRegularExpression(
                '/base64:[A-Za-z0-9+\/]{43}=/',
                $conteudo,
                basename($arquivo).' contém uma chave de aplicação.'
            );
        }
    }

    public function test_o_ambiente_em_execucao_tem_uma_chave_valida(): void
    {
        $chave = config('app.key');

        $this->assertNotEmpty($chave, 'A APP_KEY não está definida neste ambiente.');
        $this->assertStringStartsWith('base64:', $chave);
        $this->assertSame(32, strlen(base64_decode(substr($chave, 7))), 'A chave não tem 32 bytes.');
    }
}
