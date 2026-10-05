<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dá a cada usuário do projeto o papel que a coluna users.role já declara.
 *
 * O JSON guarda o e-mail, e não o id: é a chave de negócio estável do usuário.
 */
class UserRoleAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $json = File::get(database_path('seeders/json/model_has_roles.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $user = User::where('email', $item->user_email)->first();

            $user->assignRole($item->role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
