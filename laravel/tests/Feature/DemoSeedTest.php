<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Enums\UnitStatus;
use App\Enums\UseCase;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guarda a carga de demonstração: ela entra pelo mesmo caminho de quem demonstra.
 *
 * Carga de exemplo apodrece calada. Alguém renomeia uma conta, troca a ordem
 * de um JSON, acrescenta um perfil — e nada acusa, porque nenhum teste a
 * exercita: a suíte inteira monta o que precisa pela factory. O estrago só
 * aparece na apresentação, quando a senha combinada não entra.
 *
 * Este teste roda o DatabaseSeeder de verdade — e não o subconjunto de
 * referência que os demais usam — e entra pela rota de login, com a senha
 * documentada. É o único lugar do projeto onde a carga completa é exercitada.
 */
class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Aplica a carga inteira, e não só o subconjunto de referência.
     *
     * A propriedade `$seeder` da TestCase não serviria: o RefreshDatabase só
     * semeia no `migrate:fresh` que abre a execução, e quem o define é a
     * primeira classe a rodar. Numa suíte completa esta nunca é a primeira, e
     * o teste mediria uma base vazia — foi exatamente o que aconteceu.
     *
     * Então a carga é aplicada aqui, dentro da transação do teste. As tabelas
     * de referência já vêm semeadas de fora dela e precisam sair antes, ou o
     * PermissionSeeder esbarraria na unicidade do nome; o rollback do fim as
     * devolve para os testes seguintes.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $tabela) {
            DB::table($tabela)->delete();
        }

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * A senha combinada para a demonstração.
     *
     * Tem seis caracteres, abaixo do mínimo de oito que o ChangePasswordRequest
     * exige: o seeder grava o hash direto e não passa pela validação. É aceito
     * por ser carga de demonstração, e é mais uma razão para ela nunca conviver
     * com dado real.
     */
    private const SENHA = '123456';

    /**
     * As contas que a demonstração usa, uma por perfil.
     *
     * @var array<string, string>
     */
    private const CONTAS_POR_PERFIL = [
        'admin' => 'admin@gmail.com',
        'syndic' => 'syndic@gmail.com',
        'employee' => 'employee@gmail.com',
        'resident' => 'resident@gmail.com',
    ];

    public function test_cada_perfil_tem_uma_conta_que_entra_com_a_senha_combinada(): void
    {
        foreach (self::CONTAS_POR_PERFIL as $papel => $email) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => self::SENHA])
                ->assertStatus(201)
                ->assertJsonPath('data.user.email', $email)
                ->assertJsonPath('data.user.role', $papel)
                ->assertJsonPath('data.user.status', 'active');

            $this->postJson('/api/auth/logout');
        }
    }

    public function test_o_papel_do_spatie_acompanha_a_coluna_de_perfil(): void
    {
        // A coluna users.role é o que a interface lê; as tabelas do Spatie são
        // o que autoriza. Uma conta semeada com as duas em desacordo entraria
        // e receberia 403 em tudo, sem explicação na tela.
        foreach (User::all() as $user) {
            $this->assertSame(
                [$user->role->value],
                $user->getRoleNames()->all(),
                "A conta {$user->email} tem users.role e o papel do Spatie em desacordo."
            );
        }
    }

    public function test_todas_as_contas_da_carga_usam_a_mesma_senha(): void
    {
        foreach (User::all() as $user) {
            $this->assertTrue(
                Hash::check(self::SENHA, $user->password),
                "A conta {$user->email} não abre com a senha da demonstração."
            );
        }
    }

    public function test_a_conta_inativa_nao_entra(): void
    {
        // RN04 — demonstrar a recusa exige uma conta já inativa na carga. A
        // senha está certa: é a situação da conta que barra, e a resposta diz
        // isso, em vez de se confundir com credencial inválida.
        $this->postJson('/api/auth/login', ['email' => 'inactive@gmail.com', 'password' => self::SENHA])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Usuário inativo não pode acessar o sistema.');
    }

    public function test_a_conta_de_primeiro_acesso_entra_e_para_na_troca_de_senha(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'firstaccess@gmail.com', 'password' => self::SENHA])
            ->assertStatus(201);

        $this->getJson('/api/users/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_a_carga_traz_morador_com_e_sem_unidade(): void
    {
        // O middleware unit.linked só se demonstra com os dois casos na base.
        $this->assertTrue(User::where('email', 'resident@gmail.com')->first()->belongsToUnit());
        $this->assertFalse(User::where('email', 'resident3@gmail.com')->first()->belongsToUnit());
    }

    public function test_a_carga_traz_uma_concessao_individual(): void
    {
        // A tela de permissões separa o que vem do perfil do que foi concedido
        // à pessoa; sem nenhuma concessão, os dois conjuntos sairiam iguais e a
        // distinção não teria como ser mostrada.
        $sindico = User::where('email', 'syndic@gmail.com')->first();

        $this->assertSame(['view-audit-reports'], $sindico->getDirectPermissions()->pluck('name')->all());
        $this->assertFalse($sindico->role->can(UseCase::ViewAuditReports));
        $this->assertTrue($sindico->can('view-audit-reports'));
    }

    public function test_a_carga_cobre_os_estados_que_precisam_ser_demonstrados(): void
    {
        $this->assertTrue(Unit::where('status', UnitStatus::Active)->exists(), 'Nenhuma unidade ativa na carga.');
        $this->assertTrue(Unit::where('status', UnitStatus::Inactive)->exists(), 'Nenhuma unidade inativa na carga.');

        $this->assertTrue(
            UnitOccupancy::where('is_active', false)->whereNotNull('ended_at')->exists(),
            'Nenhum vínculo encerrado na carga: o histórico da unidade não teria o que mostrar.'
        );

        foreach (OccurrenceStatus::cases() as $status) {
            $this->assertDatabaseHas('occurrences', ['status' => $status->value]);
        }
    }

    public function test_as_reservas_da_carga_alcancam_o_futuro(): void
    {
        // A data é calculada a partir de hoje justamente para isto. Se alguém
        // fixá-la no JSON, a carga nasce velha e não há o que aprovar nem
        // cancelar na demonstração.
        $this->assertTrue(
            DB::table('reservations')->whereDate('date', '>', now()->toDateString())->exists(),
            'Nenhuma reserva da carga está no futuro.'
        );
    }

    public function test_nenhuma_referencia_da_carga_ficou_orfa(): void
    {
        $referencias = [
            ['unit_occupancies', 'unit_id', 'units'],
            ['unit_occupancies', 'user_id', 'users'],
            ['occurrences', 'resident_id', 'users'],
            ['occurrences', 'unit_id', 'units'],
            ['occurrences', 'assigned_employee_id', 'users'],
            ['occurrence_histories', 'occurrence_id', 'occurrences'],
            ['occurrence_histories', 'user_id', 'users'],
            ['reservations', 'common_area_id', 'common_areas'],
            ['reservations', 'resident_id', 'users'],
            ['notices', 'author_id', 'users'],
            ['logs', 'user_id', 'users'],
            ['users', 'unit_id', 'units'],
        ];

        foreach ($referencias as [$tabela, $coluna, $destino]) {
            $orfas = DB::table($tabela)
                ->whereNotNull($coluna)
                ->whereNotIn($coluna, DB::table($destino)->pluck('id'))
                ->count();

            $this->assertSame(0, $orfas, "{$tabela}.{$coluna} aponta para {$destino} que não existe.");
        }
    }

    public function test_o_atalho_da_unidade_concorda_com_o_vinculo_vigente(): void
    {
        // users.unit_id é denormalizado: quem o mantém é o vínculo. As duas
        // pontas em desacordo fariam a tela do morador mostrar uma unidade e a
        // listagem de ocupantes, outra.
        foreach (User::whereNotNull('unit_id')->get() as $user) {
            $this->assertTrue(
                UnitOccupancy::where('user_id', $user->id)
                    ->where('unit_id', $user->unit_id)
                    ->where('is_active', true)
                    ->exists(),
                "A conta {$user->email} aponta para uma unidade sem vínculo ativo que a sustente."
            );
        }

        foreach (UnitOccupancy::where('is_active', true)->get() as $vinculo) {
            $this->assertSame(
                $vinculo->unit_id,
                $vinculo->user->unit_id,
                "O vínculo ativo da unidade {$vinculo->unit_id} não se refletiu em users.unit_id."
            );
        }
    }
}
