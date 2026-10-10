<?php

namespace Tests\Feature;

use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UC01 / RF01 — a administração cadastra, consulta, edita e inativa usuários.
 */
class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        return User::factory()->admin()->create();
    }

    private function cadastro(array $attributes = []): array
    {
        return array_merge([
            'name' => 'Ana Silva',
            'email' => 'ana@condovale.test',
            'role' => UserRole::Resident->value,
        ], $attributes);
    }

    public function test_lista_os_usuarios_paginados(): void
    {
        User::factory()->count(3)->resident()->create();

        $this->actingAs($this->administrador())
            ->getJson('/api/users')
            ->assertStatus(201)
            ->assertJsonPath('total', 4)
            ->assertJsonCount(4, 'data');
    }

    public function test_a_listagem_nunca_devolve_a_senha(): void
    {
        $resposta = $this->actingAs($this->administrador())->getJson('/api/users');

        $this->assertStringNotContainsString('argon2id', $resposta->getContent());
        $resposta->assertJsonMissingPath('data.0.password');
        $resposta->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_busca_por_nome_e_email(): void
    {
        User::factory()->resident()->create(['name' => 'Bruno Oliveira', 'email' => 'bruno@condovale.test']);
        User::factory()->resident()->create(['name' => 'Carla Souza', 'email' => 'carla@condovale.test']);

        $this->actingAs($this->administrador())
            ->getJson('/api/users?search=bruno')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.email', 'bruno@condovale.test');
    }

    public function test_consulta_um_usuario(): void
    {
        $morador = User::factory()->resident()->create();

        $this->actingAs($this->administrador())
            ->getJson("/api/users/{$morador->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.id', $morador->id);
    }

    public function test_usuario_inexistente_responde_404(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/users/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Usuário 999 não encontrado.');
    }

    public function test_cadastra_com_senha_temporaria_e_envia_por_email(): void
    {
        Mail::fake();

        $resposta = $this->actingAs($this->administrador())
            ->postJson('/api/users', $this->cadastro())
            ->assertStatus(201)
            ->assertJsonPath('data.user.email', 'ana@condovale.test')
            ->assertJsonPath('data.user.must_change_password', true);

        $criado = User::where('email', 'ana@condovale.test')->first();
        $temporaria = $resposta->json('data.temporary_password');

        $this->assertNotEmpty($temporaria);
        $this->assertTrue(Hash::check($temporaria, $criado->password));

        Mail::assertSent(TemporaryPasswordMail::class, fn ($mail) => $mail->hasTo('ana@condovale.test'));
    }

    public function test_o_usuario_cadastrado_ja_nasce_com_as_autorizacoes_do_perfil(): void
    {
        Mail::fake();

        $this->actingAs($this->administrador())
            ->postJson('/api/users', $this->cadastro(['role' => UserRole::Employee->value]))
            ->assertStatus(201);

        $criado = User::where('email', 'ana@condovale.test')->first();

        $this->assertSame(['employee'], $criado->getRoleNames()->all());
        $this->assertTrue($criado->can('finish-occurrence'));
    }

    public function test_registra_o_cadastro_na_trilha_de_auditoria(): void
    {
        Mail::fake();

        $this->actingAs($this->administrador())->postJson('/api/users', $this->cadastro());

        $criado = User::where('email', 'ana@condovale.test')->first();

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::USER->value,
            'description' => "Usuário {$criado->id} Ana Silva cadastrado com perfil resident.",
        ]);
    }

    public function test_recusa_email_repetido(): void
    {
        Mail::fake();
        User::factory()->resident()->create(['email' => 'ana@condovale.test']);

        $this->actingAs($this->administrador())
            ->postJson('/api/users', $this->cadastro())
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Este e-mail já está em uso.');
    }

    public function test_edita_qualquer_dado_inclusive_o_perfil(): void
    {
        $morador = User::factory()->resident()->create();

        $this->actingAs($this->administrador())
            ->patchJson("/api/users/{$morador->id}", [
                'name' => 'Nome Corrigido',
                'role' => UserRole::Employee->value,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Nome Corrigido')
            ->assertJsonPath('data.role', 'employee');

        $atualizado = $morador->fresh();

        // A troca de perfil precisa chegar à autorização, não só à coluna.
        $this->assertSame(['employee'], $atualizado->getRoleNames()->all());
        $this->assertTrue($atualizado->can('finish-occurrence'));
        $this->assertFalse($atualizado->can('request-reservation'));
    }

    public function test_inativa_sem_apagar(): void
    {
        $morador = User::factory()->resident()->create();

        $this->actingAs($this->administrador())
            ->postJson("/api/users/{$morador->id}/deactivate")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('users', ['id' => $morador->id, 'status' => 'inactive']);
    }

    public function test_rn04_a_sessao_de_quem_for_inativado_cai_na_requisicao_seguinte(): void
    {
        $morador = User::factory()->resident()->create(['email' => 'morador@condovale.test']);
        $morador->forceFill(['password' => 'condovale-2026'])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'morador@condovale.test',
            'password' => 'condovale-2026',
        ])->assertStatus(201);

        $this->actingAs($this->administrador())
            ->postJson("/api/users/{$morador->id}/deactivate")
            ->assertStatus(201);

        // Em produção cada requisição sobe um container novo e o guard relê o
        // usuário; no teste o container é reaproveitado, então sem isto o
        // guard devolveria o objeto que resolveu antes da inativação.
        $this->app['auth']->forgetGuards();

        $this->actingAs($morador->fresh())
            ->getJson('/api/auth/session')
            ->assertStatus(403);
    }

    public function test_reativa_pelo_patch(): void
    {
        $morador = User::factory()->resident()->create(['status' => UserStatus::Inactive]);

        $this->actingAs($this->administrador())
            ->patchJson("/api/users/{$morador->id}", ['status' => UserStatus::Active->value])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_recusa_inativar_duas_vezes(): void
    {
        $morador = User::factory()->resident()->create(['status' => UserStatus::Inactive]);

        $this->actingAs($this->administrador())
            ->postJson("/api/users/{$morador->id}/deactivate")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este usuário já está inativo.');
    }

    public function test_o_administrador_nao_se_inativa(): void
    {
        // Quem se inativa não volta: a sessão cai e a conta deixa de
        // autenticar, sem caminho de recuperação pela interface.
        $admin = $this->administrador();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson("/api/users/{$admin->id}/deactivate")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não pode inativar o seu próprio usuário.');
    }

    public function test_nao_inativa_o_ultimo_administrador_ativo(): void
    {
        // Sem administrador ativo não sobra quem cadastre, edite ou reative
        // ninguém: o sistema só voltaria por acesso direto ao banco.
        $admin = $this->administrador();
        $outro = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson("/api/users/{$outro->id}/deactivate")
            ->assertStatus(201);

        $terceiro = User::factory()->admin()->create();

        $this->actingAs($terceiro)
            ->postJson("/api/users/{$admin->id}/deactivate")
            ->assertStatus(201);

        $this->actingAs($terceiro)
            ->postJson("/api/users/{$terceiro->id}/deactivate")
            ->assertStatus(403);
    }

    public function test_a_trava_do_ultimo_administrador_vale_tambem_pelo_patch(): void
    {
        // Duas portas para a mesma mudança precisam da mesma regra, senão a
        // porta fechada numa fica aberta na outra.
        $admin = $this->administrador();
        $outro = User::factory()->resident()->create();

        $this->actingAs($outro)
            ->patchJson("/api/users/{$admin->id}", ['status' => UserStatus::Inactive->value])
            ->assertStatus(403);
    }

    public function test_somente_o_administrador_gerencia_usuarios(): void
    {
        $alvo = User::factory()->resident()->create();

        foreach ([UserRole::Resident, UserRole::Employee, UserRole::Syndic] as $papel) {
            $this->actingAs(User::factory()->create(['role' => $papel]))
                ->getJson('/api/users')
                ->assertStatus(403);

            $this->actingAs(User::factory()->create(['role' => $papel]))
                ->postJson("/api/users/{$alvo->id}/deactivate")
                ->assertStatus(403);
        }
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }
}
