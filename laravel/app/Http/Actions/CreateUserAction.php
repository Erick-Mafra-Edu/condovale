<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Enums\UserStatus;
use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * RF01 / UC01 — a administração cadastra usuários.
 *
 * A conta nasce com uma **senha temporária** e marcada para troca: a pessoa
 * entra com ela uma vez e não alcança nada além da tela de nova senha. Nunca
 * existe senha padrão, e a senha temporária nunca é escolhida por quem cadastra
 * — assim ninguém além do destinatário chega a conhecê-la como definitiva.
 */
class CreateUserAction
{
    /**
     * Comprimento da senha temporária. Ela é descartável e trocada no primeiro
     * acesso, mas precisa resistir a tentativa enquanto o e-mail não chega.
     */
    private const TAMANHO_SENHA_TEMPORARIA = 16;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{user: User, temporary_password: string}
     */
    public static function execute(array $attributes): array
    {
        $temporaria = Str::password(self::TAMANHO_SENHA_TEMPORARIA, symbols: false);

        $user = User::create([
            'name' => $attributes['name'],
            'email' => Str::lower(trim($attributes['email'])),
            'role' => $attributes['role'],
            'status' => $attributes['status'] ?? UserStatus::Active->value,
            'unit_id' => $attributes['unit_id'] ?? null,
            'password' => $temporaria,
            'must_change_password' => true,
        ]);

        SyncUserRoleAction::execute($user);

        Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporaria));

        $description = "Usuário {$user->id} {$user->name} cadastrado com perfil {$user->role->value}.";
        CreateLogAction::execute(TypeLogEnum::USER->value, $description, $user);

        return ['user' => $user, 'temporary_password' => $temporaria];
    }
}
