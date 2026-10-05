<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Actions\SyncUserRoleAction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * UC01 / RF01 — operações administrativas autorizadas conforme o perfil.
 *
 * A autorização é lida das tabelas do Spatie; a matriz que as alimenta é a do
 * diagrama de casos de uso, em App\Enums\UserRole::useCases(), e a paridade
 * dela com o frontend é travada pelo PermissionMatrixTest.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rota de teste própria: mede o middleware de autorização, e não o
        // módulo que por acaso o usa.
        Route::middleware(['api', 'auth', 'user.active', 'use.case:manage-residents'])
            ->get('/api/teste-administrativo', fn () => response()->json(['ok' => true]));
    }

    public function test_o_seeder_materializa_a_matriz_do_diagrama_no_banco(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (UserRole::cases() as $role) {
            $esperado = array_map(fn ($useCase) => $useCase->value, $role->useCases());
            $gravado = Role::findByName($role->value, 'web')->permissions->pluck('name')->all();

            sort($esperado);
            sort($gravado);

            $this->assertSame($esperado, $gravado, "Permissões divergentes para o papel {$role->value}.");
        }
    }

    public function test_o_usuario_criado_ja_nasce_com_as_autorizacoes_do_perfil(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame(['admin'], $admin->getRoleNames()->all());
        $this->assertTrue($admin->can('manage-residents'));
        $this->assertFalse($admin->can('request-reservation'));
    }

    public function test_so_o_perfil_autorizado_alcanca_a_operacao_administrativa(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/teste-administrativo')
            ->assertStatus(200);

        foreach ([UserRole::Resident, UserRole::Employee, UserRole::Syndic] as $papel) {
            $this->actingAs(User::factory()->create(['role' => $papel]))
                ->getJson('/api/teste-administrativo')
                ->assertStatus(403)
                ->assertJsonPath('message', 'Você não tem permissão para executar esta operação.');
        }
    }

    public function test_rn04_usuario_inativo_nao_passa_mesmo_tendo_a_permissao(): void
    {
        $admin = User::factory()->admin()->create(['status' => UserStatus::Inactive]);

        $this->assertTrue($admin->can('manage-residents'));

        $this->actingAs($admin)
            ->getJson('/api/teste-administrativo')
            ->assertStatus(403);
    }

    public function test_concessao_individual_autoriza_sem_mudar_o_perfil(): void
    {
        // É isto que o banco traz e o enum não trazia: a administração libera
        // um caso de uso para uma pessoa específica sem promovê-la a
        // administrador nem precisar de um deploy.
        $this->seed(RolePermissionSeeder::class);

        $sindico = User::factory()->syndic()->create();

        $this->actingAs($sindico)->getJson('/api/teste-administrativo')->assertStatus(403);

        $sindico->givePermissionTo('manage-residents');

        $this->assertSame(['syndic'], $sindico->fresh()->getRoleNames()->all());

        $this->actingAs($sindico->fresh())
            ->getJson('/api/teste-administrativo')
            ->assertStatus(200);
    }

    public function test_a_concessao_individual_pode_ser_revogada(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $sindico = User::factory()->syndic()->create();
        $sindico->givePermissionTo('manage-residents');
        $sindico->revokePermissionTo('manage-residents');

        $this->actingAs($sindico->fresh())
            ->getJson('/api/teste-administrativo')
            ->assertStatus(403);
    }

    public function test_trocar_o_perfil_retira_as_autorizacoes_do_perfil_anterior(): void
    {
        $user = User::factory()->admin()->create();
        $this->assertTrue($user->can('manage-residents'));

        $user->update(['role' => UserRole::Resident]);
        SyncUserRoleAction::execute($user);

        $atualizado = $user->fresh();

        $this->assertSame(['resident'], $atualizado->getRoleNames()->all());
        $this->assertFalse($atualizado->can('manage-residents'));
        $this->assertTrue($atualizado->can('request-reservation'));
    }

    public function test_o_seeder_e_idempotente(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $permissoes = Permission::count();
        $papeis = Role::count();

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($permissoes, Permission::count());
        $this->assertSame($papeis, Role::count());
    }

    public function test_permissao_ausente_nega_em_vez_de_estourar(): void
    {
        // Uma permissão que ainda não foi semeada se comporta como ausente, e a
        // rota responde 403 — e não 500, que é o que o hasPermissionTo do
        // Spatie faria ao lançar PermissionDoesNotExist.
        $user = User::factory()->admin()->create();

        $this->assertFalse($user->can('caso-de-uso-inexistente'));
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/teste-administrativo')->assertStatus(401);
    }
}
