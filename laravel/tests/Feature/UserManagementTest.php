<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_somente_admin_pode_gerenciar_e_inativar_usuario(): void
    {
        $employee = User::factory()->employee()->create();
        $this->actingAs($employee)->getJson('/api/users')->assertStatus(403);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'João Funcionário',
                'email' => 'joao@condovale.test',
                'role' => UserRole::Employee->value,
                'password' => 'condovale-2026',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.email', 'joao@condovale.test');

        $created = User::where('email', 'joao@condovale.test')->firstOrFail();
        User::factory()->count(11)->employee()->create();

        $this->actingAs($admin)->getJson('/api/users')
            ->assertStatus(201)
            ->assertJsonCount(14, 'data');
        $this->actingAs($admin)
            ->patchJson("/api/users/{$created->id}", ['name' => 'João Atualizado'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'João Atualizado');
        $this->actingAs($admin)
            ->postJson("/api/users/{$created->id}/deactivate")
            ->assertStatus(201)
            ->assertJsonPath('data.status', UserStatus::Inactive->value);

        $this->assertDatabaseHas('users', ['id' => $created->id, 'status' => 'inactive']);
    }
}
