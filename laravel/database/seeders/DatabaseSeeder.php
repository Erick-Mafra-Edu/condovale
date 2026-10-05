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
     * nomes das colunas, e cria com create() — carga que falha alto é melhor
     * que carga que engole divergência.
     *
     * A ordem abaixo é a das dependências, não a alfabética. Os ids saem da
     * posição da linha no arquivo, e outros JSON apontam para eles por número:
     * acrescentar registro sempre no fim, nunca no meio.
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
            UnitOccupancySeeder::class,

            CommonAreaSeeder::class,
            ReservationSeeder::class,

            OccurrenceSeeder::class,
            OccurrenceHistorySeeder::class,

            NoticeSeeder::class,
            LogSeeder::class,
        ]);
    }
}
