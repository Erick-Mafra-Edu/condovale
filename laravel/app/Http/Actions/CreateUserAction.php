<?php

namespace App\Http\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * RF01 — the administration registers users.
 *
 * Without a password chosen by the administrator the account is created with a
 * random secret: it exists but cannot authenticate until a password is set.
 * There is never a known default password (RNF02).
 */
class CreateUserAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function execute(array $attributes): User
    {
        return User::create([
            'name' => $attributes['name'],
            'email' => Str::lower($attributes['email']),
            'role' => $attributes['role'],
            'status' => $attributes['status'] ?? UserStatus::Active->value,
            'unit_id' => $attributes['unit_id'] ?? null,
            'password' => $attributes['password'] ?? Str::random(40),
        ]);
    }
}
