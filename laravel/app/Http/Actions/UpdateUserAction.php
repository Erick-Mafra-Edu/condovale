<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * RF01 / UC01 — a administração edita qualquer dado de qualquer usuário.
 *
 * É a rota ampla, oposta ao /users/me: aqui se muda perfil, situação e unidade
 * de outra pessoa. A senha não entra — ela só é definida pelo próprio dono, na
 * troca do primeiro acesso ou depois.
 */
class UpdateUserAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function execute(User $user, array $attributes): User
    {
        if (array_key_exists('email', $attributes)) {
            $attributes['email'] = Str::lower(trim($attributes['email']));
        }

        $papelMudou = array_key_exists('role', $attributes) && $attributes['role'] !== $user->role->value;
        $inativando = self::estaInativando($user, $attributes);

        if ($inativando) {
            DeactivateUserAction::checkCanDeactivate($user);
        }

        $user->update($attributes);

        // users.role e o vínculo do Spatie são duas representações do mesmo
        // fato; quem as mantém em acordo é esta Action, na mesma transação.
        if ($papelMudou) {
            SyncUserRoleAction::execute($user);
        }

        $description = "Usuário {$user->id} {$user->name} editado.";
        CreateLogAction::execute(TypeLogEnum::USER->value, $description, (object) $attributes);

        return $user->refresh();
    }

    /**
     * A reativação também passa por aqui, pelo mesmo PATCH: não há endpoint
     * próprio para ela, só o campo `status`.
     */
    private static function estaInativando(User $user, array $attributes): bool
    {
        if (! array_key_exists('status', $attributes)) {
            return false;
        }

        return $attributes['status'] === UserStatus::Inactive->value && $user->isActive();
    }
}
