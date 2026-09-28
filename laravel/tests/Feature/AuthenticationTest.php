<?php

namespace Tests\Feature;

use App\Enums\TypeLogEnum;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * UC02 / RF02 — autenticação por e-mail e senha, com a RN04 e a RNF02.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA = 'condovale-2026';

    protected function setUp(): void
    {
        parent::setUp();

        // O limitador é chaveado pela conta e o cache sobrevive dentro do teste;
        // sem limpar, um caso de senha errada derrubaria o seguinte.
        RateLimiter::clear('login:'.$this->email());
    }

    private function email(): string
    {
        return 'morador@condovale.test';
    }

    private function criarUsuario(array $attributes = []): User
    {
        return User::factory()->resident()->create(array_merge([
            'email' => $this->email(),
            'password' => self::SENHA,
        ], $attributes));
    }

    public function test_autentica_usuario_ativo_com_as_credenciais_corretas(): void
    {
        $user = $this->criarUsuario(['name' => 'Ana Silva']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => self::SENHA,
        ]);

        $response->assertStatus(201)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $this->email())
            ->assertJsonPath('data.user.role', 'resident')
            ->assertJsonPath('data.user.status', 'active');

        $this->assertAuthenticatedAs($user);
    }

    public function test_nunca_devolve_a_senha_nem_o_remember_token(): void
    {
        $this->criarUsuario();

        $response = $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => self::SENHA,
        ]);

        $response->assertJsonMissingPath('data.user.password');
        $response->assertJsonMissingPath('data.user.remember_token');
    }

    public function test_registra_o_login_na_trilha_de_auditoria(): void
    {
        $user = $this->criarUsuario(['name' => 'Ana Silva']);

        $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => self::SENHA,
        ]);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::LOGIN->value,
            'user_id' => $user->id,
            'description' => "Usuário {$user->id} Ana Silva autenticado.",
        ]);
    }

    public function test_recusa_a_senha_errada(): void
    {
        $this->criarUsuario();

        $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => 'senha-errada',
        ])->assertStatus(401)->assertJsonPath('message', 'E-mail ou senha inválidos.');

        $this->assertGuest();
    }

    public function test_recusa_e_mail_desconhecido_com_a_mesma_mensagem_da_senha_errada(): void
    {
        // Mensagens distintas transformariam o login num verificador de quais
        // e-mails existem no condomínio.
        $this->criarUsuario();

        $desconhecido = $this->postJson('/api/auth/login', [
            'email' => 'ninguem@condovale.test',
            'password' => self::SENHA,
        ]);

        $senhaErrada = $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => 'senha-errada',
        ]);

        $desconhecido->assertStatus(401);
        $this->assertSame(
            $senhaErrada->json('message'),
            $desconhecido->json('message'),
        );
    }

    public function test_rn04_recusa_usuario_inativo_mesmo_com_a_senha_correta(): void
    {
        $this->criarUsuario(['status' => UserStatus::Inactive]);

        $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => self::SENHA,
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Usuário inativo não pode acessar o sistema.');

        $this->assertGuest();
    }

    public function test_rn04_encerra_a_sessao_de_quem_for_inativado_durante_o_uso(): void
    {
        $user = $this->criarUsuario();

        $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => self::SENHA,
        ])->assertStatus(201);

        $user->update(['status' => UserStatus::Inactive]);

        // Em produção cada requisição sobe um container novo e o guard relê o
        // usuário pelo id guardado na sessão. No teste o container é
        // reaproveitado entre as chamadas, então sem isto o guard devolveria o
        // objeto que resolveu no login — ainda ativo — e o teste passaria a
        // medir o cache do guard em vez da regra.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/session')->assertStatus(403);
        $this->assertGuest();
    }

    public function test_rnf02_guarda_a_senha_com_argon2id_e_nunca_em_claro(): void
    {
        $user = $this->criarUsuario();

        $this->assertNotSame(self::SENHA, $user->password);
        $this->assertStringStartsWith('$argon2id$', $user->password);
        $this->assertTrue(Hash::check(self::SENHA, $user->password));
    }

    public function test_valida_os_campos_em_portugues(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Informe o e-mail.')
            ->assertJsonPath('errors.password.0', 'Informe a senha.');
    }

    public function test_sessao_de_visitante_responde_sem_dados_em_vez_de_erro(): void
    {
        $this->getJson('/api/auth/session')
            ->assertStatus(201)
            ->assertJson(['status' => true])
            ->assertJsonMissingPath('data');
    }

    public function test_sessao_devolve_o_usuario_autenticado(): void
    {
        $user = $this->criarUsuario();

        $this->actingAs($user)
            ->getJson('/api/auth/session')
            ->assertStatus(201)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_encerra_a_sessao_e_registra_a_saida(): void
    {
        $user = $this->criarUsuario(['name' => 'Ana Silva']);

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertStatus(201);

        $this->assertGuest();
        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::LOGOUT->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_sair_sem_estar_autenticado_nao_e_erro(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(201);

        $this->assertDatabaseCount('logs', 0);
    }

    public function test_limita_as_tentativas_por_conta_e_nao_por_endereco(): void
    {
        $this->criarUsuario();

        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->postJson('/api/auth/login', [
                'email' => $this->email(),
                'password' => 'senha-errada',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email' => $this->email(),
            'password' => 'senha-errada',
        ])->assertStatus(429);

        // Outra conta, do mesmo endereço, continua podendo tentar: é isso que
        // impede uma pessoa errando a senha de travar o condomínio inteiro.
        User::factory()->resident()->create([
            'email' => 'outro@condovale.test',
            'password' => self::SENHA,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'outro@condovale.test',
            'password' => self::SENHA,
        ])->assertStatus(201);
    }
}
