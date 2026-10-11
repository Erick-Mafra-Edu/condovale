<?php

namespace Tests\Feature;

use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Models\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UC01 / RF01 — cada um edita o próprio cadastro, e o primeiro acesso obriga
 * a trocar a senha temporária.
 */
class OwnProfileTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA = 'condovale-2026';

    private function autenticado(array $attributes = []): User
    {
        $user = User::factory()->resident()->create(array_merge([
            'email' => 'eu@condovale.test',
        ], $attributes));

        $user->forceFill(['password' => self::SENHA])->save();

        return $user->fresh();
    }

    public function test_todo_perfil_edita_o_proprio_cadastro(): void
    {
        // A matriz passou a dar update-own-profile aos quatro perfis: antes,
        // só o morador conseguia corrigir o próprio nome.
        foreach (UserRole::cases() as $papel) {
            $user = User::factory()->create(['role' => $papel]);

            $this->actingAs($user)
                ->patchJson('/api/users/me', [
                    'name' => 'Nome Novo',
                    'email' => "{$papel->value}@condovale.test",
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.name', 'Nome Novo');
        }
    }

    public function test_a_edicao_do_proprio_cadastro_vai_para_a_auditoria(): void
    {
        // RNF04. Trocar o próprio e-mail troca a identidade de login, e isto
        // era a única mutação dos módulos entregues que não deixava rastro: a
        // auditoria não tinha como reconstruir por qual conta alguém entrava
        // antes da troca.
        $user = $this->autenticado(['name' => 'Ana Silva']);

        $this->actingAs($user)
            ->patchJson('/api/users/me', ['name' => 'Ana Silva Costa', 'email' => 'ana@condovale.test'])
            ->assertStatus(201);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::USER->value,
            'user_id' => $user->id,
            'description' => "Usuário {$user->id} Ana Silva Costa editou o próprio cadastro.",
        ]);

        $registro = Log::where('user_id', $user->id)->latest('id')->first();

        // Os dados saem da coluna como estrutura, e nao como JSON dentro de
        // JSON: a CreateLogAction codificava antes de gravar e o cast `array`
        // do model codificava de novo, de modo que a leitura devolvia uma
        // string. O relatorio de auditoria receberia aspas escapadas.
        $this->assertIsArray($registro->data_log);
        $this->assertSame('eu@condovale.test', $registro->data_log['de']['email']);
        $this->assertSame('ana@condovale.test', $registro->data_log['para']['email']);
    }

    public function test_o_cadastro_proprio_vem_da_sessao_e_nao_da_requisicao(): void
    {
        $eu = $this->autenticado();
        $outro = User::factory()->resident()->create(['name' => 'Outra Pessoa']);

        $this->actingAs($eu)
            ->getJson('/api/users/me')
            ->assertStatus(201)
            ->assertJsonPath('data.id', $eu->id);

        // Não existe como alcançar o cadastro alheio por esta porta: o id nunca
        // é lido do corpo nem da URL.
        $this->actingAs($eu)
            ->patchJson('/api/users/me', ['name' => 'Invadido', 'email' => 'eu@condovale.test'])
            ->assertStatus(201);

        $this->assertSame('Outra Pessoa', $outro->fresh()->name);
    }

    public function test_recusa_assumir_email_de_outra_pessoa(): void
    {
        $this->autenticado();
        $outro = User::factory()->resident()->create(['email' => 'ocupado@condovale.test']);

        $this->actingAs(User::where('email', 'eu@condovale.test')->first())
            ->patchJson('/api/users/me', ['name' => 'Eu', 'email' => $outro->email])
            ->assertStatus(422);
    }

    public function test_troca_a_propria_senha(): void
    {
        $eu = $this->autenticado();

        $this->actingAs($eu)
            ->patchJson('/api/users/me/password', [
                'current_password' => self::SENHA,
                'password' => 'nova-senha-2026',
                'password_confirmation' => 'nova-senha-2026',
            ])
            ->assertStatus(201);

        $this->assertTrue(Hash::check('nova-senha-2026', $eu->fresh()->password));
    }

    public function test_exige_a_senha_atual_para_trocar(): void
    {
        // Sem isso, uma sessão sequestrada viraria posse permanente da conta.
        $eu = $this->autenticado();

        $this->actingAs($eu)
            ->patchJson('/api/users/me/password', [
                'current_password' => 'chute',
                'password' => 'nova-senha-2026',
                'password_confirmation' => 'nova-senha-2026',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A senha atual não confere.');

        $this->assertTrue(Hash::check(self::SENHA, $eu->fresh()->password));
    }

    public function test_recusa_repetir_a_senha_atual(): void
    {
        $eu = $this->autenticado();

        $this->actingAs($eu)
            ->patchJson('/api/users/me/password', [
                'current_password' => self::SENHA,
                'password' => self::SENHA,
                'password_confirmation' => self::SENHA,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A nova senha deve ser diferente da atual.');
    }

    public function test_registra_a_troca_de_senha_na_auditoria(): void
    {
        $eu = $this->autenticado(['name' => 'Ana Silva']);

        $this->actingAs($eu)->patchJson('/api/users/me/password', [
            'current_password' => self::SENHA,
            'password' => 'nova-senha-2026',
            'password_confirmation' => 'nova-senha-2026',
        ]);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::USER->value,
            'description' => "Usuário {$eu->id} Ana Silva alterou a própria senha.",
        ]);
    }

    public function test_a_senha_temporaria_prende_a_sessao_na_troca(): void
    {
        // A senha temporária viajou por e-mail e pode ter passado por caixas de
        // entrada e registros de servidor: deixá-la funcionando como senha
        // comum transformaria um e-mail antigo em acesso permanente.
        $eu = $this->autenticado(['must_change_password' => true]);

        $this->actingAs($eu)
            ->getJson('/api/users/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_a_sessao_continua_legivel_e_anuncia_a_troca_pendente(): void
    {
        // /auth/session fica fora do bloqueio de propósito: é por ela que o
        // cliente descobre quem está autenticado. Bloqueá-la deixaria a
        // interface sem saber sequer que precisa levar à tela de nova senha.
        $eu = $this->autenticado(['must_change_password' => true]);

        $this->actingAs($eu)
            ->getJson('/api/auth/session')
            ->assertStatus(201)
            ->assertJsonPath('data.user.must_change_password', true);
    }

    public function test_a_rota_de_troca_continua_alcancavel_com_a_temporaria(): void
    {
        // É a saída do bloqueio: se ela estivesse atrás do mesmo middleware, a
        // pessoa ficaria trancada do lado de fora da única porta que destranca.
        $eu = $this->autenticado(['must_change_password' => true]);

        $this->actingAs($eu)
            ->patchJson('/api/users/me/password', [
                'current_password' => self::SENHA,
                'password' => 'minha-senha-2026',
                'password_confirmation' => 'minha-senha-2026',
            ])
            ->assertStatus(201);

        $atualizado = $eu->fresh();

        $this->assertFalse($atualizado->must_change_password);

        $this->actingAs($atualizado)
            ->getJson('/api/users/me')
            ->assertStatus(201);
    }

    public function test_o_administrador_com_temporaria_tambem_fica_preso(): void
    {
        // A trava vale pelo estado da conta, não pelo perfil: senão a conta
        // mais poderosa seria a única que escaparia dela.
        $admin = $this->autenticado(['role' => UserRole::Admin, 'must_change_password' => true]);

        $this->actingAs($admin)
            ->getJson('/api/users')
            ->assertStatus(403)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/users/me')->assertStatus(401);
        $this->patchJson('/api/users/me/password')->assertStatus(401);
    }
}
