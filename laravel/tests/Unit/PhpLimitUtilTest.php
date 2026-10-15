<?php

namespace Tests\Unit;

use App\Http\Utils\PhpLimitUtil;
use PHPUnit\Framework\TestCase;

/**
 * O teto de upload anunciado é o menor entre o configurado e o php.ini.
 *
 * Anunciar mais do que o servidor recebe faz o PHP descartar o arquivo antes
 * da validação, e a mensagem vira "selecione a imagem" para quem acabou de
 * selecioná-la.
 */
class PhpLimitUtilTest extends TestCase
{
    public function test_converte_a_notacao_do_php_ini(): void
    {
        $this->assertSame(2 * 1024 * 1024, PhpLimitUtil::bytes('2M'));
        $this->assertSame(128 * 1024 * 1024, PhpLimitUtil::bytes('128m'));
        $this->assertSame(1024 ** 3, PhpLimitUtil::bytes('1G'));
        $this->assertSame(512 * 1024, PhpLimitUtil::bytes('512K'));
        $this->assertSame(1000, PhpLimitUtil::bytes('1000'));
    }

    public function test_sem_limite_e_nulo(): void
    {
        $this->assertNull(PhpLimitUtil::bytes('-1'));
        $this->assertNull(PhpLimitUtil::bytes(''));
        $this->assertNull(PhpLimitUtil::bytes(false));
    }

    public function test_vale_o_menor_entre_configurado_e_php_ini(): void
    {
        // O caso do desenvolvimento: 5 MB configurados, php.ini aceita 2.
        $this->assertSame(2048, PhpLimitUtil::uploadMaxKilobytes(5120, '2M', '8M'));

        // O caso relatado no InfinityFree: o php.ini aceita 10, vale o configurado.
        $this->assertSame(5120, PhpLimitUtil::uploadMaxKilobytes(5120, '10M', '10M'));
    }

    public function test_o_post_max_size_tambem_limita(): void
    {
        // O arquivo viaja dentro da requisição: um post_max_size menor que o
        // upload_max_filesize é o que manda.
        $this->assertSame(3072, PhpLimitUtil::uploadMaxKilobytes(5120, '10M', '3M'));
    }

    public function test_limite_ilimitado_do_php_nao_entra_na_conta(): void
    {
        $this->assertSame(5120, PhpLimitUtil::uploadMaxKilobytes(5120, '-1', '-1'));
    }
}
