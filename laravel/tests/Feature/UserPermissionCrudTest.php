<?php

namespace Tests\Feature;

use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC01 / RF01 — o administrador concede e revoga casos de uso por pessoa.
 */
class UserPermissionCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_lista_o_catalogo_de_casos_de_uso(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/permissions?limit=100')
            ->assertStatus(201)
            ->assertJsonPath('total', 22);
    }

    public function test_lista_os_perfis_com_os_casos_de_uso_de_cada_um(): void
    {
        $resposta = $this->actingAs($this->administrador())
            ->getJson('/api/roles')
            ->assertStatus(201);

        $perfis = collect($resposta->json('data'))->keyBy('name');

        $this->assertCount(4, $perfis);
        $this->assertCount(11, $perfis['admin']['permissions']);
        $this->assertContains('request-reservation', $perfis['resident']['permissions']);
    }

    public function test_mostra_o_que_vem_do_perfil_e_o_que_foi_concedido_a_pessoa(): void
    {
        $sindico = User::factory()->syndic()->create();
        $sindico->givePermissionTo('manage-residents');

        $this->actingAs($this->administrador())
            ->getJson("/api/users/{$sindico->id}/permissions")
            ->assertStatus(201)
            ->assertJsonPath('data.role', 'syndic')
            ->assertJsonPath('data.direct', ['manage-residents'])
            ->assertJsonPath('data.from_role', ['generate-reports', 'login', 'publish-notices'])
            ->assertJsonCount(4, 'data.effective');
    }

    public function test_concede_um_caso_de_uso_a_uma_pessoa(): void
    {
        $sindico = User::factory()->syndic()->create();

        $this->actingAs($this->administrador())
            ->postJson("/api/users/{$sindico->id}/permissions", ['permission' => 'manage-units'])
            ->assertStatus(201)
            ->assertJsonPath('data.direct', ['manage-units']);

        $this->assertTrue($sindico->fresh()->can('manage-units'));
        // O perfil não muda: a exceção é individual.
        $this->assertSame(['syndic'], $sindico->fresh()->getRoleNames()->all());
    }

    public function test_registra_a_concessao_na_trilha_de_auditoria(): void
    {
        $sindico = User::factory()->syndic()->create(['name' => 'Carlos Síndico']);

        $this->actingAs($this->administrador())
            ->postJson("/api/users/{$sindico->id}/permissions", ['permission' => 'manage-units']);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::USER->value,
            'description' => "Caso de uso manage-units concedido ao usuário {$sindico->id} Carlos Síndico.",
        ]);
    }

    public function test_recusa_conceder_o_que_o_perfil_ja_da(): void
    {
        // Uma linha que não muda nada e que sobreviveria a um rebaixamento de
        // perfil, deixando a pessoa autorizada por um caminho esquecido.
        $sindico = User::factory()->syndic()->create();

        $this->actingAs($this->administrador())
            ->postJson("/api/users/{$sindico->id}/permissions", ['permission' => 'publish-notices'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'O perfil Síndico já concede publish-notices.');
    }

    public function test_recusa_conceder_duas_vezes(): void
    {
        $sindico = User::factory()->syndic()->create();
        $admin = $this->administrador();

        $this->actingAs($admin)->postJson("/api/users/{$sindico->id}/permissions", ['permission' => 'manage-units'])
            ->assertStatus(201);

        $this->actingAs($admin)->postJson("/api/users/{$sindico->id}/permissions", ['permission' => 'manage-units'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'O usuário já possui manage-units.');
    }

    public function test_recusa_caso_de_uso_inexistente(): void
    {
        $sindico = User::factory()->syndic()->create();

        $this->actingAs($this->administrador())
            ->postJson("/api/users/{$sindico->id}/permissions", ['permission' => 'inventado'])
            ->assertStatus(422)
            ->assertJsonPath('errors.permission.0', 'O caso de uso informado não existe.');
    }

    public function test_revoga_a_concessao_individual(): void
    {
        $sindico = User::factory()->syndic()->create();
        $sindico->givePermissionTo('manage-units');

        $this->actingAs($this->administrador())
            ->deleteJson("/api/users/{$sindico->id}/permissions/manage-units")
            ->assertStatus(201)
            ->assertJsonPath('data.direct', []);

        $this->assertFalse($sindico->fresh()->can('manage-units'));
    }

    public function test_recusa_revogar_o_que_veio_do_perfil(): void
    {
        // Retirar do indivíduo algo que o perfil dá exigiria trocar o perfil;
        // aceitar aqui daria a impressão de ter funcionado sem efeito nenhum.
        $sindico = User::factory()->syndic()->create();

        $this->actingAs($this->administrador())
            ->deleteJson("/api/users/{$sindico->id}/permissions/publish-notices")
            ->assertStatus(409)
            ->assertJsonPath('message', 'O usuário não possui a concessão individual de publish-notices.');
    }

    public function test_o_administrador_nao_se_desautoriza(): void
    {
        // Sem esta trava o último administrador se tranca para fora: não
        // sobraria ninguém capaz de devolver a permissão pela interface.
        $admin = $this->administrador();
        $admin->givePermissionTo('manage-residents');

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$admin->id}/permissions/manage-residents")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não pode retirar de si mesmo a autorização de gerenciar usuários.');
    }

    public function test_troca_o_perfil_e_substitui_as_autorizacoes(): void
    {
        $morador = User::factory()->resident()->create();

        $this->actingAs($this->administrador())
            ->patchJson("/api/users/{$morador->id}/role", ['role' => UserRole::Employee->value])
            ->assertStatus(201)
            ->assertJsonPath('data.role', 'employee');

        $atualizado = $morador->fresh();

        $this->assertSame(['employee'], $atualizado->getRoleNames()->all());
        $this->assertTrue($atualizado->can('finish-occurrence'));
        $this->assertFalse($atualizado->can('request-reservation'));
    }

    public function test_usuario_inexistente_responde_404(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/users/999/permissions')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Usuário 999 não encontrado.');
    }

    public function test_somente_o_administrador_gerencia_permissoes(): void
    {
        $alvo = User::factory()->resident()->create();

        foreach ([UserRole::Resident, UserRole::Employee, UserRole::Syndic] as $papel) {
            $this->actingAs(User::factory()->create(['role' => $papel]))
                ->postJson("/api/users/{$alvo->id}/permissions", ['permission' => 'manage-units'])
                ->assertStatus(403);
        }
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/permissions')->assertStatus(401);
    }
}
