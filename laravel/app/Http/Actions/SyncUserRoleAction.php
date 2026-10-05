<?php

namespace App\Http\Actions;

use App\Enums\UseCase;
use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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
 * duas. Este é esse lugar, e é também o único que materializa a matriz do
 * diagrama de casos de uso no banco — o seeder e a factory passam por aqui.
 */
class SyncUserRoleAction
{
    public static function execute(User $user): User
    {
        self::ensureRole($user->role);

        // syncRoles, e não assignRole: quando o perfil muda, o papel antigo
        // precisa sair junto, senão a pessoa acumula as autorizações dos dois.
        $user->syncRoles([$user->role->value]);

        return $user;
    }

    /**
     * Garante que o papel exista no banco com exatamente as permissões que a
     * matriz lhe dá.
     *
     * Criar o papel vazio seria pior que falhar: a pessoa existiria com um
     * perfil que não autoriza nada, e ninguém perceberia até alguém reclamar
     * de 403. Por isso o papel nasce já com os casos de uso dele.
     */
    public static function ensureRole(UserRole $role): Role
    {
        // O findOrCreate do Spatie procura na coleção que o registrar mantém em
        // memória, não no banco. Se essa coleção foi carregada quando a tabela
        // ainda estava vazia — o que acontece logo depois de um migrate:fresh —
        // ele conclui que a permissão não existe e tenta inserir uma que já
        // está lá, estourando a restrição de unicidade. Limpar antes de
        // resolver é o que faz a consulta voltar ao banco.
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $model = Role::findOrCreate($role->value, 'web');

        $permissions = array_map(
            fn (UseCase $useCase) => Permission::findOrCreate($useCase->value, 'web'),
            $role->useCases(),
        );

        $model->syncPermissions($permissions);

        // E de novo no fim, para que uma checagem feita logo em seguida —
        // numa mesma requisição ou num mesmo teste — enxergue o que foi
        // concedido agora.
        $registrar->forgetCachedPermissions();

        return $model;
    }
}
