<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Casos de uso do diagrama funcional, um por linha de permissions.json.
 *
 * O arquivo espelha App\Enums\UseCase, e o PermissionJsonMatrixTest trava as
 * duas listas: divergir faz a suíte falhar, não a autorização.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/permissions.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'name' => $item->name,
                'guard_name' => $item->guard_name,
            ];

            Permission::create($array);
        }
    }
}
