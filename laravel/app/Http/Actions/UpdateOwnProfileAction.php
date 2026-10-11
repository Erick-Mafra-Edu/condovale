<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
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

        $anterior = ['name' => $user->name, 'email' => $user->email];

        $user->update([
            'name' => $attributes['name'],
            'email' => $email,
        ]);

        // RNF04 — trocar o próprio e-mail troca a identidade de login, e
        // ficava sem rastro: era a única mutação dos módulos entregues que não
        // chegava à trilha de auditoria. O valor anterior vai junto, porque é
        // o que permite reconstruir por qual conta alguém entrava antes.
        $description = "Usuário {$user->id} {$user->name} editou o próprio cadastro.";
        CreateLogAction::execute(TypeLogEnum::USER->value, $description, [
            'de' => $anterior,
            'para' => ['name' => $user->name, 'email' => $user->email],
        ]);

        return $user->refresh();
    }
}
