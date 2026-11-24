<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ResolvesReferences;
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
    use ResolvesReferences;

    public function run(): void
    {
        // O Spatie resolve nomes pela coleção que mantém em memória, carregada
        // antes desta carga. Sem limpar, ele não enxerga o que o
        // PermissionSeeder acabou de inserir.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $json = File::get(database_path('seeders/json/role_has_permissions.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'role_id' => $this->roleId($item->role, 'role_has_permissions.json'),
                'permission_id' => $this->permissionId($item->permission, 'role_has_permissions.json'),
            ];

            // A chave é o próprio par: reaplicar não duplica o vínculo, e um
            // caso de uso concedido a um papel no JSON chega a produção.
            DB::table('role_has_permissions')->updateOrInsert($array);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
