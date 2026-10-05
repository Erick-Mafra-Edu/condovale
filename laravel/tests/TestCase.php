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
}
