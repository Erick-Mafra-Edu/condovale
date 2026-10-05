<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dá a cada usuário do projeto o papel correspondente nas tabelas do Spatie.
 *
 * A coluna `users.role` continua sendo onde se lê o perfil de alguém — é ela
 * que vai no payload da API e que o frontend consome. O vínculo do Spatie é o
 * que a autorização consulta. Quem mantém as duas em acordo na escrita é a
 * CreateUserAction; este seeder faz o mesmo para a carga inicial e serve para
 * reconciliar a base caso a coluna tenha sido alterada por fora.
 */
class UserRoleAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->each(function (User $user) {
            // syncRoles, e não assignRole: se o perfil da pessoa mudou, o papel
            // antigo precisa sair junto, senão ela acumula autorizações.
            $user->syncRoles([$user->role->value]);
        });
    }
}
