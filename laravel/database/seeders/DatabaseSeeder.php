<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds, in the order the foreign keys require.
     *
     * Cada seeder lê um JSON de database/seeders/json, cujas chaves são os
     * nomes das colunas.
     *
     * As tabelas de **referência** — permissões, papéis, a matriz entre eles,
     * tipos de ocorrência e áreas comuns — usam updateOrCreate pela chave de
     * negócio: são configuração do produto, e uma mudança no JSON chega a
     * produção reaplicando só o seeder (`db:seed --class=...`), sem recriar a
     * base nem duplicar linha. O **dado de demonstração** (pessoas, unidades,
     * chamados, reservas, comunicados) cria com create(): só roda numa base
     * recém-criada, e ali carga que falha alto é melhor que carga que engole
     * divergência.
     *
     * A ordem abaixo é a das dependências, não a alfabética. O que um JSON
     * aponta no outro é a **chave de negócio** — o e-mail do usuário, o código
     * da unidade, o nome da área —, resolvida em id na hora de semear. Por
     * isso a ordem das linhas dentro de um arquivo não é contrato: acrescentar
     * um registro no meio é seguro, e uma referência quebrada estoura dizendo
     * qual arquivo cita o quê.
     *
     * Esta carga é de **demonstração**: traz uma conta por perfil com senha
     * conhecida, mais as variações de estado — inativo, primeiro acesso, sem
     * unidade — que cada regra precisa para ser mostrada funcionando.
     */
    public function run(): void
    {
        $this->call([
            // Referência: papéis e permissões existem antes de alguém usá-los.
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,

            UnitSeeder::class,
            UserSeeder::class,
            UserRoleAssignmentSeeder::class,
            UserPermissionAssignmentSeeder::class,
            UnitOccupancySeeder::class,

            CommonAreaSeeder::class,
            ReservationSeeder::class,

            OccurrenceTypeSeeder::class,
            OccurrenceSeeder::class,
            OccurrenceHistorySeeder::class,

            NoticeSeeder::class,
            LogSeeder::class,
        ]);
    }
}
