<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Enums\UseCase;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\AuthUtil;
use App\Models\Permission;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * UC01 / RF01 — o administrador concede e revoga casos de uso por pessoa.
 *
 * A concessão é **direta**, somada ao que o perfil já dá e registrada numa
 * linha própria. É isso que permite abrir uma exceção para alguém sem
 * promovê-la de perfil: o síndico que precisa, só ele, mexer no cadastro de
 * moradores não vira administrador por causa disso.
 */
class GrantPermissionAction
{
    public static function execute(User $user, string $permissionName): User
    {
        $permission = self::findPermission($permissionName);

        self::checkNotAlreadyGrantedByRole($user, $permission);
        self::checkNotAlreadyGrantedDirectly($user, $permission);

        $user->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $description = "Caso de uso {$permission->name} concedido ao usuário {$user->id} {$user->name}.";
        CreateLogAction::execute(TypeLogEnum::USER->value, $description, ['permission' => $permission->name]);

        return $user->fresh();
    }

    public static function revoke(User $user, string $permissionName): User
    {
        $permission = self::findPermission($permissionName);

        if (! $user->hasDirectPermission($permission)) {
            throw BusinessRuleException::conflict(
                "O usuário não possui a concessão individual de {$permission->name}."
            );
        }

        self::checkNotLockingOutSelf($user, $permission);

        $user->revokePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $description = "Caso de uso {$permission->name} revogado do usuário {$user->id} {$user->name}.";
        CreateLogAction::execute(TypeLogEnum::USER->value, $description, ['permission' => $permission->name]);

        return $user->fresh();
    }

    private static function findPermission(string $name): Permission
    {
        $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();

        if (! $permission) {
            throw BusinessRuleException::notFound("Caso de uso {$name} não existe.");
        }

        return $permission;
    }

    /**
     * Conceder o que o perfil já dá criaria uma linha que não muda nada e que
     * sobreviveria a uma troca de perfil — a pessoa rebaixada continuaria com
     * a autorização, agora por um caminho que ninguém lembra de ter criado.
     */
    private static function checkNotAlreadyGrantedByRole(User $user, Permission $permission): void
    {
        if ($user->roles->first()?->hasPermissionTo($permission)) {
            throw BusinessRuleException::conflict(
                "O perfil {$user->role->label()} já concede {$permission->name}."
            );
        }
    }

    private static function checkNotAlreadyGrantedDirectly(User $user, Permission $permission): void
    {
        if ($user->hasDirectPermission($permission)) {
            throw BusinessRuleException::conflict("O usuário já possui {$permission->name}.");
        }
    }

    /**
     * Ninguém tira de si mesmo a autorização de administrar usuários.
     *
     * Sem esta trava, o último administrador consegue se desautorizar e o
     * sistema fica sem quem possa conceder qualquer permissão de volta — não
     * há caminho de recuperação pela interface.
     */
    private static function checkNotLockingOutSelf(User $user, Permission $permission): void
    {
        $eu = AuthUtil::id();

        if ($eu === $user->id && $permission->name === UseCase::ManageResidents->value) {
            throw BusinessRuleException::forbidden(
                'Você não pode retirar de si mesmo a autorização de gerenciar usuários.'
            );
        }
    }
}
