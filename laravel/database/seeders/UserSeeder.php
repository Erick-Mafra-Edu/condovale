<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $unit101 = Unit::where('code', 'A-101')->first();
        $unit102 = Unit::where('code', 'A-102')->first();
        $unit201 = Unit::where('code', 'B-201')->first();

        // Administrator
        User::firstOrCreate(
            ['email' => 'admin@condovale.com'],
            [
                'name' => 'Administrador Geral',
                'password' => 'senha123',
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
            ]
        );

        // Síndico
        User::firstOrCreate(
            ['email' => 'sindico@condovale.com'],
            [
                'name' => 'Carlos Síndico',
                'password' => 'senha123',
                'role' => UserRole::Syndic,
                'status' => UserStatus::Active,
            ]
        );

        // Employees
        User::firstOrCreate(
            ['email' => 'portaria@condovale.com'],
            [
                'name' => 'João Portaria',
                'password' => 'senha123',
                'role' => UserRole::Employee,
                'status' => UserStatus::Active,
            ]
        );

        User::firstOrCreate(
            ['email' => 'manutencao@condovale.com'],
            [
                'name' => 'Pedro Manutenção',
                'password' => 'senha123',
                'role' => UserRole::Employee,
                'status' => UserStatus::Active,
            ]
        );

        // Residents
        User::firstOrCreate(
            ['email' => 'morador1@condovale.com'],
            [
                'name' => 'Ana Silva',
                'password' => 'senha123',
                'role' => UserRole::Resident,
                'status' => UserStatus::Active,
                'unit_id' => $unit101?->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'morador2@condovale.com'],
            [
                'name' => 'Bruno Oliveira',
                'password' => 'senha123',
                'role' => UserRole::Resident,
                'status' => UserStatus::Active,
                'unit_id' => $unit102?->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'morador3@condovale.com'],
            [
                'name' => 'Carla Souza',
                'password' => 'senha123',
                'role' => UserRole::Resident,
                'status' => UserStatus::Active,
                'unit_id' => $unit201?->id,
            ]
        );
    }
}
