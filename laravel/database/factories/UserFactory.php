<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Actions\SyncUserRoleAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('condovale'),
            'role' => UserRole::Resident,
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Dá ao usuário criado o papel correspondente nas tabelas do Spatie.
     *
     * Sem isto, um usuário de teste teria o perfil na coluna `users.role` mas
     * nenhuma autorização, e toda rota protegida responderia 403 — o teste
     * estaria medindo a falta do vínculo, não a regra.
     *
     * Os papéis são criados na hora caso ainda não existam, para o teste não
     * depender de o RolePermissionSeeder ter rodado.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => SyncUserRoleAction::execute($user));
    }

    public function resident(): static
    {
        return $this->state(['role' => UserRole::Resident]);
    }

    public function employee(): static
    {
        return $this->state(['role' => UserRole::Employee]);
    }

    public function syndic(): static
    {
        return $this->state(['role' => UserRole::Syndic]);
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => UserStatus::Inactive]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
