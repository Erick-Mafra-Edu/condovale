<?php

namespace App\Http\Actions;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mantém a autorização do Spatie em acordo com a coluna `users.role`.
 *
 * São duas representações do mesmo fato, e é proposital: a coluna é o que a
 * API devolve e o frontend lê; o vínculo do Spatie é o que a autorização
 * consulta — é ele que permite conceder um caso de uso a uma pessoa específica
 * sem alterar o perfil dela nem fazer deploy.
 *
 * Duas representações só não divergem se houver um lugar único que escreva nas
 * duas. Este é esse lugar.
 *
 * Os papéis em si vêm da carga (RoleSeeder), não daqui: se o papel não existir,
 * o Spatie lança e a falha aparece — é uma base sem a carga de referência, não
 * algo que esta Action deva remendar criando papel vazio.
 */
class SyncUserRoleAction
{
    public static function execute(User $user): User
    {
        // syncRoles, e não assignRole: quando o perfil muda, o papel antigo
        // precisa sair junto, senão a pessoa acumula as autorizações dos dois.
        $user->syncRoles([$user->role->value]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
