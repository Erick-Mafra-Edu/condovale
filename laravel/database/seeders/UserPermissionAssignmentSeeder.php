<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

/**
 * As concessões individuais: casos de uso dados a uma pessoa, não ao perfil.
 *
 * É o que a tela de permissões do administrador manipula, e a carga precisa
 * trazer pelo menos uma para que a diferença entre "veio do perfil" e "foi
 * concedido a você" possa ser demonstrada — com os dois conjuntos vazios, a
 * tela mostraria sempre a mesma coisa.
 */
class UserPermissionAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $json = File::get(database_path('seeders/json/model_has_permissions.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $user = User::where('email', $item->user_email)->first();

            $user->givePermissionTo($item->permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
