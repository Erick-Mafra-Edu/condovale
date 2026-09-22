<?php

namespace App\Http\Actions;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * RN17 — the authenticated user updates only their own name and e-mail.
 *
 * Role, status and unit are structural and stay untouched even if the request
 * carries them; the identity is the one of the session, never an id sent by
 * the client.
 */
class UpdateOwnProfileAction
{
    /**
     * @param  array{name: string, email: string}  $attributes
     */
    public static function execute(User $user, array $attributes): User
    {
        $email = Str::lower(trim($attributes['email']));

        $taken = User::query()
            ->where('email', $email)
            ->whereKeyNot($user->id)
            ->exists();

        if ($taken) {
            throw BusinessRuleException::unprocessable('Este e-mail já está em uso.');
        }

        $user->update([
            'name' => $attributes['name'],
            'email' => $email,
        ]);

        return $user->refresh();
    }
}
