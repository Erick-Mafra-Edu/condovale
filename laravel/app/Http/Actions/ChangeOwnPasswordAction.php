<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * A pessoa define a própria senha — no primeiro acesso ou depois.
 *
 * É o único caminho pelo qual uma senha é escolhida no sistema: a
 * administração cadastra e gera uma temporária, mas nunca escolhe a senha de
 * ninguém.
 */
class ChangeOwnPasswordAction
{
    public static function execute(Request $request, User $user, string $atual, string $nova): User
    {
        self::checkCurrentPassword($user, $atual);
        self::checkIsDifferent($atual, $nova);

        $user->update([
            'password' => $nova,
            'must_change_password' => false,
        ]);

        // A sessão é regenerada porque a credencial mudou: um identificador
        // obtido antes da troca não deve continuar valendo depois dela.
        $request->session()->regenerate();

        $description = "Usuário {$user->id} {$user->name} alterou a própria senha.";
        CreateLogAction::execute(TypeLogEnum::USER->value, $description);

        return $user->refresh();
    }

    /**
     * Exigir a senha atual é o que impede que uma sessão sequestrada vire posse
     * permanente da conta: sem ela, quem roubasse o cookie trocaria a senha e
     * expulsaria o dono.
     */
    private static function checkCurrentPassword(User $user, string $atual): void
    {
        if (! Hash::check($atual, $user->password)) {
            throw BusinessRuleException::unprocessable('A senha atual não confere.');
        }
    }

    private static function checkIsDifferent(string $atual, string $nova): void
    {
        if (hash_equals($atual, $nova)) {
            throw BusinessRuleException::unprocessable('A nova senha deve ser diferente da atual.');
        }
    }
}
