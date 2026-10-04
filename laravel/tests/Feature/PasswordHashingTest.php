<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Actions\CreateUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RNF02 — senhas armazenadas exclusivamente como hash Argon2id, com
 * verificação segura no login (UC02 / RF02).
 */
class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA = 'condovale-2026';

    public function test_o_driver_de_hash_da_aplicacao_e_argon2id(): void
    {
        $this->assertSame('argon2id', config('hashing.driver'));
        $this->assertTrue(defined('PASSWORD_ARGON2ID'), 'O PHP desta máquina não oferece Argon2id.');
    }

    public function test_a_senha_nunca_e_gravada_em_claro(): void
    {
        $user = User::factory()->resident()->create(['password' => self::SENHA]);

        $this->assertNotSame(self::SENHA, $user->password);
        $this->assertStringStartsWith('$argon2id$', $user->password);

        // Confere direto na linha do banco, e não só no objeto: o cast poderia
        // estar mascarando um valor em claro já persistido.
        $gravado = \DB::table('users')->where('id', $user->id)->value('password');
        $this->assertStringStartsWith('$argon2id$', $gravado);
    }

    public function test_o_cadastro_pela_action_tambem_grava_argon2id(): void
    {
        $user = CreateUserAction::execute([
            'name' => 'Ana Silva',
            'email' => 'ana@condovale.test',
            'role' => UserRole::Resident->value,
            'password' => self::SENHA,
        ]);

        $this->assertStringStartsWith('$argon2id$', $user->password);
    }

    public function test_conta_criada_sem_senha_recebe_segredo_aleatorio_e_nao_padrao(): void
    {
        $primeiro = CreateUserAction::execute([
            'name' => 'Sem Senha Um',
            'email' => 'um@condovale.test',
            'role' => UserRole::Resident->value,
        ]);

        $segundo = CreateUserAction::execute([
            'name' => 'Sem Senha Dois',
            'email' => 'dois@condovale.test',
            'role' => UserRole::Resident->value,
        ]);

        $this->assertStringStartsWith('$argon2id$', $primeiro->password);
        $this->assertNotSame($primeiro->password, $segundo->password);
    }

    public function test_o_hash_usa_os_parametros_configurados(): void
    {
        $user = User::factory()->resident()->create(['password' => self::SENHA]);
        $argon = config('hashing.argon');

        $this->assertStringContainsString(
            "m={$argon['memory']},t={$argon['time']},p={$argon['threads']}",
            $user->password
        );
    }

    public function test_a_verificacao_aceita_a_senha_certa_e_recusa_a_errada(): void
    {
        $user = User::factory()->resident()->create(['password' => self::SENHA]);

        $this->assertTrue(Hash::check(self::SENHA, $user->password));
        $this->assertFalse(Hash::check('outra-senha', $user->password));
    }

    public function test_duas_contas_com_a_mesma_senha_geram_hashes_diferentes(): void
    {
        // Prova que o salt é por registro: hashes iguais permitiriam descobrir
        // de uma vez todo mundo que usa a mesma senha.
        $primeiro = User::factory()->resident()->create(['password' => self::SENHA]);
        $segundo = User::factory()->resident()->create(['password' => self::SENHA]);

        $this->assertNotSame($primeiro->password, $segundo->password);
    }

    public function test_o_login_regrava_o_hash_quando_os_parametros_ficam_mais_fortes(): void
    {
        $user = User::factory()->resident()->create(['email' => 'rehash@condovale.test']);

        // Hash gerado com custo menor que o configurado, como ficaria uma conta
        // antiga depois de o projeto endurecer os parâmetros.
        $fraco = password_hash(self::SENHA, PASSWORD_ARGON2ID, [
            'memory_cost' => 8192,
            'time_cost' => 1,
            'threads' => 1,
        ]);

        \DB::table('users')->where('id', $user->id)->update(['password' => $fraco]);

        $this->assertTrue(Hash::needsRehash($fraco));

        $this->postJson('/api/auth/login', [
            'email' => 'rehash@condovale.test',
            'password' => self::SENHA,
        ])->assertStatus(201);

        $atual = \DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame($fraco, $atual);
        $this->assertFalse(Hash::needsRehash($atual));
        $this->assertTrue(Hash::check(self::SENHA, $atual));
    }

    public function test_hash_de_outro_algoritmo_nao_autentica(): void
    {
        // "Exclusivamente Argon2id" tem consequência prática: um hash bcrypt
        // sobrevivente de outro sistema não serve para entrar. A verificação
        // configurada com verify=true recusa o algoritmo errado em vez de
        // aceitá-lo silenciosamente.
        $user = User::factory()->resident()->create(['email' => 'bcrypt@condovale.test']);

        \DB::table('users')->where('id', $user->id)->update([
            'password' => password_hash(self::SENHA, PASSWORD_BCRYPT),
        ]);

        $resposta = $this->postJson('/api/auth/login', [
            'email' => 'bcrypt@condovale.test',
            'password' => self::SENHA,
        ]);

        // A recusa é a mesma de qualquer credencial inválida, e não um 500
        // relatando erro de banco: a conta não entra e o caminho de volta é a
        // administração redefinir a senha.
        $resposta->assertStatus(401)->assertJsonPath('message', 'E-mail ou senha inválidos.');
        $this->assertStringNotContainsString('Argon2id algorithm', $resposta->getContent());
        $this->assertGuest();
    }

    public function test_a_senha_nunca_sai_numa_resposta_da_api(): void
    {
        $user = User::factory()->resident()->create([
            'email' => 'payload@condovale.test',
            'password' => self::SENHA,
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'payload@condovale.test',
            'password' => self::SENHA,
        ]);

        $login->assertJsonMissingPath('data.user.password');
        $this->assertStringNotContainsString('argon2id', $login->getContent());
        $this->assertStringNotContainsString(self::SENHA, $login->getContent());

        $this->actingAs($user)
            ->getJson('/api/auth/session')
            ->assertJsonMissingPath('data.user.password');
    }
}
