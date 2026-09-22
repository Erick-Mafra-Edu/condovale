<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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
