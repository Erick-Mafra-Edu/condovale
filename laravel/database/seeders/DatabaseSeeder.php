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
     * Each module brings its own seeder plus the JSON of its table, in
     * database/seeders/json, and registers the class in the list below. The
     * reference tables come first, then the tables that depend on them.
     */
    public function run(): void
    {
        $this->call([
            // Papéis e permissões primeiro: os usuários são vinculados a eles
            // logo em seguida, e o vínculo exige que o papel já exista.
            RolePermissionSeeder::class,
            UnitSeeder::class,
            UserSeeder::class,
            UserRoleAssignmentSeeder::class,
            UnitOccupancySeeder::class,
            CommonAreaSeeder::class,
            ReservationSeeder::class,
            OccurrenceSeeder::class,
            NoticeSeeder::class,
            LogSeeder::class,
        ]);
    }
}
