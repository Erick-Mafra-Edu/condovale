<?php

namespace Tests\Unit;

use App\Http\Utils\TemporaryPasswordUtil;
use PHPUnit\Framework\TestCase;

/**
 * A senha temporária é ditada por quem cadastrou, então o formato privilegia a
 * transcrição sem erro. Estes testes cobram o preço dessa escolha.
 */
class TemporaryPasswordTest extends TestCase
{
    /**
     * Piso do espaço de busca.
     *
     * Encolher a lista de palavras enfraquece toda senha temporária do sistema
     * sem mudar uma linha de lógica — é o tipo de perda que passa despercebida
     * num diff e que este teste existe para barrar.
     */
    private const ESPACO_MINIMO = 100_000;

    public function test_tem_oito_caracteres(): void
    {
        foreach (range(1, 50) as $ignorada) {
            $this->assertSame(8, strlen(TemporaryPasswordUtil::gerar()));
        }
    }

    public function test_e_palavra_seguida_de_tres_digitos(): void
    {
        foreach (range(1, 50) as $ignorada) {
            $this->assertMatchesRegularExpression('/^[a-z]{5}\d{3}$/', TemporaryPasswordUtil::gerar());
        }
    }

    public function test_nao_tem_acento_nem_cedilha(): void
    {
        // A senha é ditada e digitada: acento e cedilha viram erro de
        // transcrição, e maiúscula vira dúvida de quem anota.
        foreach (range(1, 50) as $ignorada) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', TemporaryPasswordUtil::gerar());
        }
    }

    public function test_nao_repete_na_pratica(): void
    {
        $geradas = [];

        foreach (range(1, 200) as $ignorada) {
            $geradas[] = TemporaryPasswordUtil::gerar();
        }

        // Com o espaço atual, 200 sorteios repetindo seria sinal de gerador
        // preso num valor — não de azar.
        $this->assertGreaterThan(190, count(array_unique($geradas)));
    }

    public function test_o_espaco_de_busca_nao_encolheu(): void
    {
        $this->assertGreaterThanOrEqual(
            self::ESPACO_MINIMO,
            TemporaryPasswordUtil::espacoDeBusca(),
            'A lista de palavras encolheu e com ela a força da senha temporária.'
        );
    }
}
