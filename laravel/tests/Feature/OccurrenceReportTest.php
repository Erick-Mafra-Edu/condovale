<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\OccurrenceType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF06 / UC20 — consulta de ocorrências e manutenções para os relatórios.
 *
 * O período é o de abertura do chamado. Os indicadores olham o período
 * inteiro; tipo e status filtram só a lista, como na tela de relatório.
 */
class OccurrenceReportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/reports/occurrences';

    private function gestor(): User
    {
        return User::factory()->syndic()->create();
    }

    private function chamado(string $abertoEm, array $attributes = []): Occurrence
    {
        return Occurrence::factory()->create(array_merge(['created_at' => $abertoEm], $attributes));
    }

    private function relatorio(array $parametros, ?User $quem = null)
    {
        $parametros += ['from' => '2026-09-01', 'to' => '2026-09-30'];

        return $this->actingAs($quem ?? $this->gestor())->getJson(self::URL.'?'.http_build_query($parametros));
    }

    public function test_sindico_e_administracao_geram_o_relatorio(): void
    {
        foreach ([User::factory()->syndic()->create(), User::factory()->admin()->create()] as $gestor) {
            $this->relatorio([], $gestor)
                ->assertStatus(201)
                ->assertJsonPath('status', true)
                ->assertJsonPath('message', 'Relatório de ocorrências de 01/09/2026 a 30/09/2026.');
        }
    }

    public function test_morador_e_funcionario_nao_geram_relatorio(): void
    {
        foreach ([User::factory()->resident()->create(), User::factory()->employee()->create()] as $usuario) {
            $this->relatorio([], $usuario)->assertStatus(403);
        }
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30')->assertStatus(401);
    }

    public function test_exige_um_periodo_valido(): void
    {
        $gestor = $this->gestor();

        $this->actingAs($gestor)->getJson(self::URL)
            ->assertStatus(422)
            ->assertJsonPath('errors.from.0', 'Informe a data inicial do período.')
            ->assertJsonPath('errors.to.0', 'Informe a data final do período.');

        $this->relatorio(['from' => '2026-09-30', 'to' => '2026-09-01'], $gestor)
            ->assertStatus(422)
            ->assertJsonPath('errors.to.0', 'A data final não pode ser anterior à data inicial.');

        $this->relatorio(['from' => '01/09/2026'], $gestor)
            ->assertStatus(422)
            ->assertJsonPath('errors.from.0', 'A data inicial deve estar no formato AAAA-MM-DD.');
    }

    public function test_recusa_filtros_que_nao_existem(): void
    {
        $gestor = $this->gestor();

        $this->relatorio(['occurrence_type_id' => 999], $gestor)
            ->assertStatus(422)
            ->assertJsonPath('errors.occurrence_type_id.0', 'O tipo de ocorrência informado não existe.');

        $this->relatorio(['status' => 'esquecido'], $gestor)
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'O status informado não existe.');

        $this->relatorio(['limit' => 101], $gestor)->assertStatus(422);
    }

    public function test_o_periodo_inclui_o_primeiro_e_o_ultimo_dia_inteiros(): void
    {
        $this->chamado('2026-08-31 23:59:59');
        $this->chamado('2026-09-01 00:00:00');
        $this->chamado('2026-09-30 23:59:59');
        $this->chamado('2026-10-01 00:00:00');

        $this->relatorio([])
            ->assertJsonPath('data.period', ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.occurrences.total', 2);
    }

    public function test_resumo_e_contagem_por_status(): void
    {
        $this->chamado('2026-09-02', ['status' => OccurrenceStatus::Open]);
        $this->chamado('2026-09-03', ['status' => OccurrenceStatus::Analysis]);
        $this->chamado('2026-09-04', ['status' => OccurrenceStatus::InProgress]);
        $this->chamado('2026-09-05', ['status' => OccurrenceStatus::Completed, 'resolved' => true, 'completed_at' => '2026-09-06']);
        $this->chamado('2026-09-06', ['status' => OccurrenceStatus::Completed, 'resolved' => false, 'completed_at' => '2026-09-07']);
        $this->chamado('2026-09-07', ['status' => OccurrenceStatus::Cancelled]);

        $resposta = $this->relatorio([])
            ->assertJsonPath('data.summary.total', 6)
            ->assertJsonPath('data.summary.open', 3)
            ->assertJsonPath('data.summary.completed', 2)
            ->assertJsonPath('data.summary.cancelled', 1)
            ->assertJsonPath('data.summary.resolved', 1)
            ->assertJsonPath('data.summary.unresolved', 1);

        // Todos os status aparecem, mesmo em zero, na ordem do fluxo.
        $this->assertSame([
            ['status' => 'open', 'label' => 'Aberto', 'total' => 1],
            ['status' => 'analysis', 'label' => 'Em análise', 'total' => 1],
            ['status' => 'assigned', 'label' => 'Atribuído', 'total' => 0],
            ['status' => 'in_progress', 'label' => 'Em andamento', 'total' => 1],
            ['status' => 'completed', 'label' => 'Concluído', 'total' => 2],
            ['status' => 'cancelled', 'label' => 'Cancelado', 'total' => 1],
        ], $resposta->json('data.by_status'));
    }

    public function test_concluida_sem_desfecho_informado_conta_como_resolvida(): void
    {
        // É o padrão da finalização: "não resolvido" precisa ser informado.
        $this->chamado('2026-09-05', ['status' => OccurrenceStatus::Completed, 'resolved' => null, 'completed_at' => '2026-09-06']);

        $this->relatorio([])
            ->assertJsonPath('data.summary.resolved', 1)
            ->assertJsonPath('data.summary.unresolved', 0);
    }

    public function test_tempo_medio_da_abertura_a_conclusao(): void
    {
        $this->chamado('2026-09-10 08:00:00', ['status' => OccurrenceStatus::Completed, 'completed_at' => '2026-09-10 18:00:00']);
        $this->chamado('2026-09-11 08:00:00', ['status' => OccurrenceStatus::Completed, 'completed_at' => '2026-09-12 04:00:00']);
        // Aberta e cancelada não entram na média.
        $this->chamado('2026-09-12 08:00:00', ['status' => OccurrenceStatus::Open]);
        $this->chamado('2026-09-13 08:00:00', ['status' => OccurrenceStatus::Cancelled]);

        // 10 h e 20 h. O JSON não guarda o ".0": 15.0 chega ao cliente como 15.
        $this->relatorio([])->assertJsonPath('data.summary.average_resolution_hours', 15);
    }

    public function test_sem_conclusao_no_periodo_o_tempo_medio_fica_vazio(): void
    {
        $this->chamado('2026-09-10', ['status' => OccurrenceStatus::Open]);

        $this->relatorio([])->assertJsonPath('data.summary.average_resolution_hours', null);
    }

    public function test_contagem_por_tipo_so_dos_tipos_usados_no_periodo(): void
    {
        $manutencao = OccurrenceType::factory()->create(['name' => 'Manutenção predial']);
        $barulho = OccurrenceType::factory()->create(['name' => 'Barulho e sossego']);
        $semUso = OccurrenceType::factory()->create(['name' => 'Telefonia']);
        $foraDoPeriodo = OccurrenceType::factory()->create(['name' => 'Estacionamento']);

        $this->chamado('2026-09-02', ['occurrence_type_id' => $manutencao->id]);
        $this->chamado('2026-09-03', ['occurrence_type_id' => $manutencao->id]);
        $this->chamado('2026-09-04', ['occurrence_type_id' => $barulho->id]);
        $this->chamado('2026-10-04', ['occurrence_type_id' => $foraDoPeriodo->id]);

        $this->assertSame([
            ['occurrence_type_id' => $manutencao->id, 'name' => 'Manutenção predial', 'total' => 2],
            ['occurrence_type_id' => $barulho->id, 'name' => 'Barulho e sossego', 'total' => 1],
        ], $this->relatorio([])->json('data.by_type'));

        $this->assertNotContains($semUso->id, array_column($this->relatorio([])->json('data.by_type'), 'occurrence_type_id'));
    }

    public function test_tipo_e_status_filtram_so_a_lista(): void
    {
        $manutencao = OccurrenceType::factory()->create();
        $barulho = OccurrenceType::factory()->create();

        $this->chamado('2026-09-02', ['occurrence_type_id' => $manutencao->id, 'status' => OccurrenceStatus::Open]);
        $this->chamado('2026-09-03', ['occurrence_type_id' => $manutencao->id, 'status' => OccurrenceStatus::Completed, 'completed_at' => '2026-09-04']);
        $this->chamado('2026-09-04', ['occurrence_type_id' => $barulho->id, 'status' => OccurrenceStatus::Completed, 'completed_at' => '2026-09-05']);

        $resposta = $this->relatorio(['occurrence_type_id' => $manutencao->id, 'status' => 'completed'])
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.completed', 2)
            ->assertJsonPath('data.occurrences.total', 1);

        $this->assertSame($manutencao->id, $resposta->json('data.occurrences.data.0.occurrence_type_id'));
        $this->assertSame('completed', $resposta->json('data.occurrences.data.0.status'));
    }

    public function test_lista_paginada_do_mais_recente_para_o_mais_antigo(): void
    {
        $primeiro = $this->chamado('2026-09-02 10:00:00');
        $ultimo = $this->chamado('2026-09-20 10:00:00');
        // Mesmo instante: o id desempata, para a paginação não repetir nem pular.
        $empateA = $this->chamado('2026-09-10 10:00:00');
        $empateB = $this->chamado('2026-09-10 10:00:00');

        $this->relatorio(['limit' => 3])
            ->assertJsonPath('data.occurrences.total', 4)
            ->assertJsonPath('data.occurrences.per_page', 3)
            ->assertJsonPath('data.occurrences.last_page', 2)
            ->assertJsonPath('data.occurrences.page', 1)
            ->assertJsonPath('data.occurrences.data.0.id', $ultimo->id)
            ->assertJsonPath('data.occurrences.data.1.id', $empateB->id)
            ->assertJsonPath('data.occurrences.data.2.id', $empateA->id);

        $this->relatorio(['limit' => 3, 'page' => 2])
            ->assertJsonPath('data.occurrences.page', 2)
            ->assertJsonPath('data.occurrences.data.0.id', $primeiro->id);
    }

    public function test_cada_linha_traz_o_que_o_relatorio_mostra_e_nada_alem(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $tipo = OccurrenceType::factory()->create(['name' => 'Manutenção predial']);
        $unidade = Unit::factory()->create();

        $this->chamado('2026-09-10 08:00:00', [
            'title' => 'Vazamento no corredor',
            'occurrence_type_id' => $tipo->id,
            'resident_id' => $morador->id,
            'unit_id' => $unidade->id,
            'assigned_employee_id' => $funcionario->id,
            'status' => OccurrenceStatus::Completed,
            'resolved' => true,
            'conclusion' => 'Cano trocado.',
            'completed_at' => '2026-09-11 08:00:00',
        ]);

        $linha = $this->relatorio([])->json('data.occurrences.data.0');

        $this->assertSame('Vazamento no corredor', $linha['title']);
        $this->assertSame('Concluído', $linha['status_label']);
        $this->assertSame('Cano trocado.', $linha['conclusion']);
        $this->assertTrue($linha['resolved']);
        // A natureza separa manutenção de denúncia (UC7).
        $this->assertSame(['id' => $tipo->id, 'name' => 'Manutenção predial', 'kind' => 'maintenance'], $linha['occurrence_type']);
        $this->assertSame(['id' => $morador->id, 'name' => 'Ana Silva'], $linha['resident']);
        $this->assertSame(['id' => $funcionario->id, 'name' => 'Pedro Manutenção'], $linha['assigned_employee']);
        $this->assertSame(['id' => $unidade->id, 'code' => $unidade->code], $linha['unit']);
        $this->assertNotNull($linha['completed_at']);
        $this->assertStringNotContainsString('@', json_encode($linha));
    }

    public function test_a_exportacao_traz_todas_as_linhas_do_filtro(): void
    {
        foreach (range(1, 25) as $dia) {
            $this->chamado(sprintf('2026-09-%02d', $dia));
        }

        // O navegador manda o booleano como texto.
        foreach (['true', '1'] as $valor) {
            $this->relatorio(['export' => $valor, 'limit' => 10, 'page' => 2])
                ->assertJsonPath('data.occurrences.total', 25)
                ->assertJsonPath('data.occurrences.per_page', 25)
                ->assertJsonPath('data.occurrences.page', 1)
                ->assertJsonPath('data.occurrences.last_page', 1)
                ->assertJsonCount(25, 'data.occurrences.data');
        }

        $this->relatorio(['export' => 'false'])->assertJsonCount(10, 'data.occurrences.data');
    }

    public function test_exportacao_acima_do_teto_pede_um_periodo_menor(): void
    {
        config(['reports.occurrences.max_export_rows' => 3]);

        foreach (range(1, 4) as $dia) {
            $this->chamado(sprintf('2026-09-%02d', $dia));
        }

        $this->relatorio(['export' => 'true'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O período tem 4 ocorrências, acima do máximo de 3 por exportação. Reduza o período ou use os filtros.');

        // A página comum continua funcionando com o mesmo período.
        $this->relatorio([])->assertStatus(201)->assertJsonPath('data.occurrences.total', 4);
    }

    public function test_periodo_sem_chamados_devolve_relatorio_zerado(): void
    {
        $this->relatorio([])
            ->assertStatus(201)
            ->assertJsonPath('data.summary.total', 0)
            ->assertJsonPath('data.summary.average_resolution_hours', null)
            ->assertJsonPath('data.by_type', [])
            ->assertJsonPath('data.occurrences.data', [])
            ->assertJsonPath('data.occurrences.total', 0);
    }
}
