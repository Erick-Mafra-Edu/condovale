<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditEntity;
use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
use App\Http\Actions\CreateLogAction;
use App\Http\Actions\ReviewReservationAction;
use App\Models\CommonArea;
use App\Models\Log;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * RNF04 / UC-T03 / RN16 — a trilha de auditoria: o que cada operação grava e
 * como a administração consulta.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-24 10:00:00'));
    }

    private function url(array $parametros = []): string
    {
        return '/api/audit-logs?'.http_build_query($parametros + ['from' => '2026-11-01', 'to' => '2026-11-30']);
    }

    /**
     * Grava pelo caminho real, como `$quem`, no momento `$quando`.
     */
    private function registrar(User $quem, AuditAction $acao, int $registro, string $quando, array $dados = []): void
    {
        $this->travelTo(Carbon::parse($quando));
        $this->actingAs($quem);

        CreateLogAction::execute($acao->typeLog()->value, "{$acao->label()} {$registro}.", $dados ?: null, action: $acao, subjectId: $registro);

        $this->travelBack();
    }

    // --- Gravação ---------------------------------------------------------

    public function test_a_operacao_registra_acao_registro_e_perfil_de_quem_fez(): void
    {
        $admin = User::factory()->admin()->create();

        $id = $this->actingAs($admin)->postJson('/api/units', ['code' => 'C-301', 'number' => '301', 'block' => 'Bloco C'])
            ->assertStatus(201)
            ->json('data.id');

        $log = Log::where('action', AuditAction::UnitCreated->value)->sole();

        $this->assertSame(TypeLogEnum::UNIT->value, $log->type_log_id);
        $this->assertSame($id, $log->subject_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('admin', $log->user_role);
        $this->assertSame(AuditEntity::Unit, $log->entity());
    }

    public function test_a_edicao_guarda_so_o_que_mudou_de_e_para(): void
    {
        $unidade = Unit::factory()->create(['code' => 'A-101', 'number' => '101', 'block' => 'Bloco A']);

        // O código chega em minúsculas e é normalizado para o mesmo valor: não mudou.
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/units/{$unidade->id}", ['code' => 'a-101', 'block' => 'Bloco B'])
            ->assertStatus(201);

        $log = Log::where('action', AuditAction::UnitUpdated->value)->sole();

        $this->assertSame($unidade->id, $log->subject_id);
        $this->assertSame(['de' => ['block' => 'Bloco A'], 'para' => ['block' => 'Bloco B']], $log->data_log);
    }

    public function test_a_troca_de_perfil_fica_registrada_sem_nada_oculto(): void
    {
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/users/{$funcionario->id}", ['role' => 'syndic', 'name' => 'Pedro Manutenção'])
            ->assertStatus(201);

        $log = Log::where('action', AuditAction::UserUpdated->value)->sole();

        $this->assertSame(['de' => ['role' => 'employee'], 'para' => ['role' => 'syndic']], $log->data_log);
        $this->assertStringNotContainsString('password', $log->getRawOriginal('data_log'));
    }

    public function test_horario_reenviado_igual_nao_vira_mudanca(): void
    {
        $area = CommonArea::factory()->create(['name' => 'Quadra', 'opening_time' => '08:00:00', 'closing_time' => '22:00:00']);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/common-areas/{$area->id}", ['name' => 'Quadra coberta', 'opening_time' => '08:00', 'closing_time' => '22:00'])
            ->assertStatus(201);

        $log = Log::where('action', AuditAction::CommonAreaUpdated->value)->sole();

        $this->assertSame(['de' => ['name' => 'Quadra'], 'para' => ['name' => 'Quadra coberta']], $log->data_log);
    }

    public function test_a_inativacao_registra_a_situacao_anterior(): void
    {
        $morador = User::factory()->resident()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/users/{$morador->id}/deactivate")
            ->assertStatus(201);

        $log = Log::where('action', AuditAction::UserDeactivated->value)->sole();

        $this->assertSame($morador->id, $log->subject_id);
        $this->assertSame(['de' => ['status' => 'active'], 'para' => ['status' => 'inactive']], $log->data_log);
    }

    public function test_aprovar_e_reprovar_reserva_ficam_na_auditoria(): void
    {
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create(['name' => 'Salão', 'opening_time' => '08:00:00', 'closing_time' => '22:00:00']);
        $pedido = Reservation::factory()->pending()->create([
            'common_area_id' => $area->id, 'date' => '2026-11-25', 'start_time' => '10:00:00', 'end_time' => '12:00:00',
        ]);
        $outro = Reservation::factory()->pending()->create([
            'common_area_id' => $area->id, 'date' => '2026-11-26', 'start_time' => '10:00:00', 'end_time' => '12:00:00',
        ]);

        $this->actingAs($admin);
        ReviewReservationAction::execute($pedido, ReservationStatus::Approved);
        ReviewReservationAction::execute($outro, ReservationStatus::Rejected, 'Área em manutenção.');

        $aprovada = Log::where('action', AuditAction::ReservationApproved->value)->sole();
        $this->assertSame($pedido->id, $aprovada->subject_id);
        $this->assertSame('admin', $aprovada->user_role);
        $this->assertSame("Reserva {$pedido->id} da área {$area->id} Salão para 25/11/2026, das 10:00 às 12:00, aprovada.", $aprovada->description);
        $this->assertSame(['status' => 'pending'], $aprovada->data_log['de']);
        $this->assertSame(['status' => 'approved'], $aprovada->data_log['para']);
        $this->assertSame($pedido->resident_id, $aprovada->data_log['resident_id']);

        $reprovada = Log::where('action', AuditAction::ReservationRejected->value)->sole();
        $this->assertSame($outro->id, $reprovada->subject_id);
        $this->assertSame(['status' => 'rejected', 'rejection_reason' => 'Área em manutenção.'], $reprovada->data_log['para']);
    }

    public function test_o_perfil_registrado_e_o_da_epoca_da_acao(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = User::factory()->employee()->create();

        $this->registrar($funcionario, AuditAction::OccurrenceProgressNoted, 7, '2026-11-10 09:00:00');

        $this->actingAs($admin)->patchJson("/api/users/{$funcionario->id}", ['role' => 'syndic'])->assertStatus(201);

        $this->actingAs($admin)->getJson($this->url(['role' => 'employee']))
            ->assertStatus(201)
            ->assertJsonPath('data.logs.total', 1)
            ->assertJsonPath('data.logs.data.0.user.id', $funcionario->id)
            ->assertJsonPath('data.logs.data.0.user_role', 'employee')
            ->assertJsonPath('data.logs.data.0.user_role_label', 'Funcionário');
    }

    public function test_acao_registrada_sob_outro_tipo_e_recusada(): void
    {
        $this->expectException(\LogicException::class);

        CreateLogAction::execute(TypeLogEnum::UNIT->value, 'Errado.', action: AuditAction::ReservationApproved, subjectId: 1);
    }

    public function test_a_trilha_nao_se_altera_nem_se_apaga(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        CreateLogAction::execute(TypeLogEnum::UNIT->value, 'Unidade 1 A-1 cadastrada.', action: AuditAction::UnitCreated, subjectId: 1);
        $log = Log::sole();

        try {
            $log->update(['description' => 'Nada aconteceu.']);
            $this->fail('A alteração deveria ser recusada.');
        } catch (\LogicException $e) {
            $this->assertSame('Um registro de auditoria não pode ser alterado.', $e->getMessage());
        }

        try {
            $log->delete();
            $this->fail('A exclusão deveria ser recusada.');
        } catch (\LogicException $e) {
            $this->assertSame('Um registro de auditoria não pode ser excluído.', $e->getMessage());
        }

        $this->assertDatabaseHas('logs', ['id' => $log->id, 'description' => 'Unidade 1 A-1 cadastrada.']);
    }

    /**
     * Uma gravação sem ação nem registro cai no relatório só como frase, sem
     * filtro que a encontre. Este teste pega a Action nova que esquecer.
     */
    public function test_toda_gravacao_de_log_declara_acao_e_registro(): void
    {
        $chamadas = 0;

        foreach (File::allFiles(app_path()) as $arquivo) {
            preg_match_all('/CreateLogAction::execute\((.*?)\);\s*$/ms', $arquivo->getContents(), $encontradas);

            foreach ($encontradas[1] as $argumentos) {
                $chamadas++;
                $onde = $arquivo->getRelativePathname();

                $this->assertMatchesRegularExpression('/\baction: [^\n]*AuditAction::/', $argumentos, "{$onde} grava log sem informar a ação.");
                $this->assertMatchesRegularExpression('/\bsubjectId: \S/', $argumentos, "{$onde} grava log sem informar o registro.");
            }
        }

        $this->assertGreaterThan(30, $chamadas, 'A varredura não encontrou as gravações de log.');
    }

    public function test_cada_acao_pertence_a_entidade_do_seu_nome(): void
    {
        foreach (AuditAction::cases() as $acao) {
            $this->assertNotSame('', $acao->label());

            if ($acao->typeLog() === TypeLogEnum::LOGIN || $acao->typeLog() === TypeLogEnum::LOGOUT) {
                continue;
            }

            $this->assertStringStartsWith($acao->typeLog()->entity()->value.'.', $acao->value, "{$acao->name} está no tipo errado.");
        }

        foreach (AuditEntity::cases() as $entidade) {
            $this->assertNotEmpty($entidade->typeLogIds(), "Nenhum tipo de log grava {$entidade->value}.");
        }
    }

    // --- Consulta ---------------------------------------------------------

    public function test_so_a_administracao_consulta_a_auditoria(): void
    {
        $this->actingAs(User::factory()->admin()->create())->getJson($this->url())->assertStatus(201);

        foreach (['syndic', 'employee', 'resident'] as $perfil) {
            $this->actingAs(User::factory()->{$perfil}()->create())->getJson($this->url())->assertStatus(403);
        }

        $this->app['auth']->forgetGuards();
        $this->getJson($this->url())->assertStatus(401);
    }

    public function test_o_periodo_e_obrigatorio(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/audit-logs')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from' => 'Informe a data inicial do período.', 'to' => 'Informe a data final do período.']);
    }

    public function test_lista_o_periodo_do_mais_recente_ao_mais_antigo(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana Admin']);

        $this->registrar($admin, AuditAction::UnitCreated, 1, '2026-10-31 23:59:59');
        $this->registrar($admin, AuditAction::UnitCreated, 2, '2026-11-01 00:00:00');
        $this->registrar($admin, AuditAction::UnitUpdated, 2, '2026-11-15 18:00:00', ['de' => ['block' => 'A'], 'para' => ['block' => 'B']]);
        $this->registrar($admin, AuditAction::UnitDeactivated, 2, '2026-11-30 23:59:59');
        $this->registrar($admin, AuditAction::UnitCreated, 3, '2026-12-01 00:00:00');

        $resposta = $this->actingAs($admin)->getJson($this->url())
            ->assertStatus(201)
            ->assertJsonPath('message', 'Relatório de auditoria de 01/11/2026 a 30/11/2026.')
            ->assertJsonPath('data.period', ['from' => '2026-11-01', 'to' => '2026-11-30'])
            ->assertJsonPath('data.logs.total', 3);

        $this->assertSame(
            ['unit.deactivated', 'unit.updated', 'unit.created'],
            array_column($resposta->json('data.logs.data'), 'action')
        );

        $linha = $resposta->json('data.logs.data.1');
        $this->assertSame([
            'id', 'action', 'action_label', 'entity', 'entity_label', 'entity_id', 'description',
            'user', 'user_role', 'user_role_label', 'metadata', 'created_at',
        ], array_keys($linha));
        $this->assertSame('Unidade editada', $linha['action_label']);
        $this->assertSame('unit', $linha['entity']);
        $this->assertSame('Unidade', $linha['entity_label']);
        $this->assertSame(2, $linha['entity_id']);
        $this->assertSame(['id' => $admin->id, 'name' => 'Ana Admin'], $linha['user']);
        $this->assertSame('Administrador', $linha['user_role_label']);
        $this->assertSame(['de' => ['block' => 'A'], 'para' => ['block' => 'B']], $linha['metadata']);
        $this->assertStringNotContainsString('127.0.0.1', $resposta->getContent());
    }

    public function test_filtros_recortam_a_lista_e_nao_os_indicadores(): void
    {
        $admin = User::factory()->admin()->create();
        $funcionario = User::factory()->employee()->create();
        $morador = User::factory()->resident()->create();

        $this->registrar($admin, AuditAction::UnitCreated, 5, '2026-11-02 09:00:00');
        $this->registrar($morador, AuditAction::ReservationCreated, 7, '2026-11-03 09:00:00');
        $this->registrar($admin, AuditAction::ReservationApproved, 7, '2026-11-04 09:00:00');
        $this->registrar($funcionario, AuditAction::OccurrenceProgressNoted, 7, '2026-11-05 09:00:00');

        $this->actingAs($admin);

        foreach ([
            [['entity' => 'reservation'], 2],
            [['entity' => 'reservation', 'entity_id' => 7], 2],
            // O 7 de uma ocorrência não é o 7 de uma reserva.
            [['entity' => 'occurrence', 'entity_id' => 7], 1],
            [['user_id' => $funcionario->id], 1],
            [['role' => 'admin'], 2],
            [['action' => 'reservation.approved'], 1],
        ] as [$filtro, $esperado]) {
            $this->getJson($this->url($filtro))
                ->assertStatus(201)
                ->assertJsonPath('data.logs.total', $esperado)
                // Os indicadores olham o período inteiro, como nos outros relatórios.
                ->assertJsonPath('data.summary', ['total' => 4, 'resident' => 1, 'employee' => 1, 'syndic' => 0, 'admin' => 2]);
        }

        $porEntidade = collect($this->getJson($this->url())->json('data.by_entity'))->pluck('total', 'entity');
        $this->assertSame(2, $porEntidade['reservation']);
        $this->assertSame(1, $porEntidade['occurrence']);
        $this->assertSame(1, $porEntidade['unit']);
        $this->assertSame(0, $porEntidade['notice']);
    }

    public function test_registro_sem_entidade_e_recusado(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson($this->url(['entity_id' => 7]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entity' => 'Informe a entidade do registro procurado.']);
    }

    public function test_registro_anterior_a_auditoria_estruturada_aparece_so_com_a_frase(): void
    {
        $admin = User::factory()->admin()->create();
        Log::create([
            'type_log_id' => TypeLogEnum::RESERVATION->value,
            'user_id' => $admin->id,
            'description' => 'Reserva 3 da área 1 Salão para 05/11/2026, dia inteiro, aprovada.',
        ]);

        $this->actingAs($admin)->getJson($this->url())
            ->assertStatus(201)
            ->assertJsonPath('data.logs.data.0.action', null)
            ->assertJsonPath('data.logs.data.0.action_label', null)
            ->assertJsonPath('data.logs.data.0.entity', 'reservation')
            ->assertJsonPath('data.logs.data.0.entity_id', null)
            ->assertJsonPath('data.logs.data.0.description', 'Reserva 3 da área 1 Salão para 05/11/2026, dia inteiro, aprovada.');
    }

    public function test_a_exportacao_traz_tudo_ate_o_teto(): void
    {
        config(['reports.audit.max_export_rows' => 3]);
        $admin = User::factory()->admin()->create();

        foreach ([1, 2, 3] as $dia) {
            $this->registrar($admin, AuditAction::UnitCreated, $dia, "2026-11-0{$dia} 09:00:00");
        }

        $this->actingAs($admin)->getJson($this->url(['export' => 'true', 'limit' => 1]))
            ->assertStatus(201)
            ->assertJsonPath('data.logs.total', 3)
            ->assertJsonCount(3, 'data.logs.data');

        $this->registrar($admin, AuditAction::UnitCreated, 4, '2026-11-04 09:00:00');

        $this->actingAs($admin)->getJson($this->url(['export' => 'true']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'O período tem 4 registros de auditoria, acima do máximo de 3 por exportação. Reduza o período ou use os filtros.');
    }
}
