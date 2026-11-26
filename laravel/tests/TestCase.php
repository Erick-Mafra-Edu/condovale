<?php

namespace Tests;

use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Papéis e permissões são carregados antes de cada teste que use banco.
     *
     * A autorização é lida das tabelas do Spatie: sem esta carga, todo teste de
     * rota protegida responderia 403 e estaria medindo a falta do vínculo em
     * vez da regra. Só as tabelas de referência entram — dado de domínio cada
     * teste cria pela factory.
     *
     * Vale apenas onde há `RefreshDatabase`; nos testes sem banco o Laravel
     * ignora estas propriedades.
     */
    protected bool $seed = true;

    protected string $seeder = TestDatabaseSeeder::class;

    /**
     * Cada teste monta uma aplicação inteira, cheia de referências circulares,
     * e o coletor do PHP só passa de tempos em tempos: na suíte completa, o
     * lixo dos testes anteriores chegava a dezenas de megabytes. Isso importa
     * porque o envio de imagem mede a memória livre do processo antes de
     * redimensionar (ImageUtil::fitsInMemory) e passava a recusar as fotos
     * conforme a ordem dos testes. Em produção cada requisição é um processo
     * novo; recolher o lixo aqui deixa cada teste começar do mesmo jeito.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }
}
