<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Subconjunto da carga para a suíte de testes: só as tabelas de referência.
 *
 * Os papéis e as permissões precisam existir antes de qualquer usuário, porque
 * é deles que a autorização é lida — sem esta carga, todo teste de rota
 * protegida mediria a falta do vínculo em vez da regra.
 *
 * Dado de domínio não entra aqui: cada teste cria o que precisa pela factory,
 * e depender de registro semeado faria o teste quebrar quando alguém
 * acrescentasse uma linha a um JSON.
 */
class TestDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
