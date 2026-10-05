<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matriz do diagrama de casos de uso: que papel concede que permissão.
 *
 * O JSON guarda os nomes, e não os ids, porque os ids são gerados pelo banco —
 * é o padrão de resolver a chave estrangeira pela chave de negócio.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // O Spatie resolve nomes pela coleção que mantém em memória, carregada
        // antes desta carga. Sem limpar, ele não enxerga o que o
        // PermissionSeeder acabou de inserir.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $json = File::get(database_path('seeders/json/role_has_permissions.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $roleId = Role::where('name', $item->role)->where('guard_name', 'web')->value('id');
            $permissionId = Permission::where('name', $item->permission)->where('guard_name', 'web')->value('id');

            $array = [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ];

            DB::table('role_has_permissions')->insert($array);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
