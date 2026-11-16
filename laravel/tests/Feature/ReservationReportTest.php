<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF06 / UC20 — consulta de reservas para os relatórios.
 *
 * O período é o da data da reserva. Os indicadores olham o período inteiro;
 * área e status filtram só a lista, como no relatório de ocorrências.
 */
class ReservationReportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/reports/reservations';

    private function area(string $nome): CommonArea
    {
        return CommonArea::factory()->create(['name' => $nome]);
    }

    private function reserva(string $data, array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_merge(['date' => $data], $attributes));
    }

    private function relatorio(array $parametros, ?User $quem = null)
    {
        $parametros += ['from' => '2026-09-01', 'to' => '2026-09-30'];

        return $this->actingAs($quem ?? User::factory()->syndic()->create())
            ->getJson(self::URL.'?'.http_build_query($parametros));
    }

    public function test_sindico_e_administracao_geram_o_relatorio(): void
    {
        foreach ([User::factory()->syndic()->create(), User::factory()->admin()->create()] as $gestor) {
            $this->relatorio([], $gestor)
                ->assertStatus(201)
                ->assertJsonPath('message', 'Relatório de reservas de 01/09/2026 a 30/09/2026.');
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

    public function test_exige_um_periodo_valido_e_filtros_que_existem(): void
    {
        $gestor = User::factory()->syndic()->create();

        $this->actingAs($gestor)->getJson(self::URL)
            ->assertStatus(422)
            ->assertJsonPath('errors.from.0', 'Informe a data inicial do período.');

        $this->relatorio(['from' => '2026-09-30', 'to' => '2026-09-01'], $gestor)
            ->assertStatus(422)
            ->assertJsonPath('errors.to.0', 'A data final não pode ser anterior à data inicial.');

        $this->relatorio(['common_area_id' => 999], $gestor)
            ->assertStatus(422)
            ->assertJsonPath('errors.common_area_id.0', 'A área comum informada não existe.');

        $this->relatorio(['status' => 'esquecida'], $gestor)
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'O status informado não existe.');
    }

    public function test_o_periodo_e_o_da_data_da_reserva(): void
    {
        // Pedida em agosto para setembro: é uso de setembro.
        $this->reserva('2026-09-10', ['created_at' => '2026-08-15 10:00:00']);
        // Pedida em setembro para outubro: fica de fora.
        $this->reserva('2026-10-02', ['created_at' => '2026-09-20 10:00:00']);
        $this->reserva('2026-09-01');
        $this->reserva('2026-09-30');
        $this->reserva('2026-08-31');

        $this->relatorio([])
            ->assertJsonPath('data.period', ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.reservations.total', 3);
    }

    public function test_resumo_e_contagem_por_status(): void
    {
        $this->reserva('2026-09-02', ['status' => ReservationStatus::Pending]);
        $this->reserva('2026-09-03', ['status' => ReservationStatus::Approved]);
        $this->reserva('2026-09-04', ['status' => ReservationStatus::Approved]);
        $this->reserva('2026-09-05', ['status' => ReservationStatus::Cancelled]);

        $resposta = $this->relatorio([])->assertJsonPath('data.summary', [
            'total' => 4,
            'pending' => 1,
            'approved' => 2,
            'rejected' => 0,
            'cancelled' => 1,
        ]);

        $this->assertSame([
            ['status' => 'pending', 'label' => 'Pendente', 'total' => 1],
            ['status' => 'approved', 'label' => 'Aprovada', 'total' => 2],
            ['status' => 'rejected', 'label' => 'Recusada', 'total' => 0],
            ['status' => 'cancelled', 'label' => 'Cancelada', 'total' => 1],
        ], $resposta->json('data.by_status'));
    }

    public function test_uso_por_area_inclui_as_areas_sem_reserva(): void
    {
        // A área sem uso é informação: o salão que ninguém reservou no mês.
        $churrasqueira = $this->area('Churrasqueira');
        $salao = $this->area('Salão de festas');
        $quadra = $this->area('Quadra');

        $this->reserva('2026-09-02', ['common_area_id' => $churrasqueira->id, 'status' => ReservationStatus::Approved]);
        $this->reserva('2026-09-03', ['common_area_id' => $churrasqueira->id, 'status' => ReservationStatus::Approved]);
        $this->reserva('2026-09-04', ['common_area_id' => $churrasqueira->id, 'status' => ReservationStatus::Cancelled]);
        $this->reserva('2026-09-05', ['common_area_id' => $quadra->id, 'status' => ReservationStatus::Pending]);
        // Fora do período: não conta para o salão.
        $this->reserva('2026-10-05', ['common_area_id' => $salao->id]);

        $this->assertSame([
            ['common_area_id' => $churrasqueira->id, 'name' => 'Churrasqueira', 'total' => 3, 'pending' => 0, 'approved' => 2, 'rejected' => 0, 'cancelled' => 1],
            ['common_area_id' => $quadra->id, 'name' => 'Quadra', 'total' => 1, 'pending' => 1, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0],
            ['common_area_id' => $salao->id, 'name' => 'Salão de festas', 'total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0],
        ], $this->relatorio([])->json('data.by_area'));
    }

    public function test_area_e_status_filtram_so_a_lista(): void
    {
        $churrasqueira = $this->area('Churrasqueira');
        $quadra = $this->area('Quadra');

        $this->reserva('2026-09-02', ['common_area_id' => $churrasqueira->id, 'status' => ReservationStatus::Approved]);
        $this->reserva('2026-09-03', ['common_area_id' => $churrasqueira->id, 'status' => ReservationStatus::Pending]);
        $this->reserva('2026-09-04', ['common_area_id' => $quadra->id, 'status' => ReservationStatus::Approved]);

        $resposta = $this->relatorio(['common_area_id' => $churrasqueira->id, 'status' => 'approved'])
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.approved', 2)
            ->assertJsonPath('data.reservations.total', 1);

        $this->assertSame($churrasqueira->id, $resposta->json('data.reservations.data.0.common_area_id'));
        $this->assertSame('approved', $resposta->json('data.reservations.data.0.status'));
    }

    public function test_lista_paginada_da_data_mais_recente_para_a_mais_antiga(): void
    {
        $area = $this->area('Churrasqueira');
        $antiga = $this->reserva('2026-09-02', ['common_area_id' => $area->id]);
        $manha = $this->reserva('2026-09-20', ['common_area_id' => $area->id, 'start_time' => '09:00', 'end_time' => '11:00']);
        $tarde = $this->reserva('2026-09-20', ['common_area_id' => $area->id, 'start_time' => '15:00', 'end_time' => '17:00']);

        $this->relatorio(['limit' => 2])
            ->assertJsonPath('data.reservations.total', 3)
            ->assertJsonPath('data.reservations.last_page', 2)
            ->assertJsonPath('data.reservations.data.0.id', $tarde->id)
            ->assertJsonPath('data.reservations.data.1.id', $manha->id);

        $this->relatorio(['limit' => 2, 'page' => 2])
            ->assertJsonPath('data.reservations.data.0.id', $antiga->id);
    }

    public function test_cada_linha_traz_o_que_o_relatorio_mostra_e_nada_alem(): void
    {
        $unidade = Unit::factory()->create();
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva', 'unit_id' => $unidade->id]);
        $area = $this->area('Salão de festas');

        $this->reserva('2026-09-12', [
            'common_area_id' => $area->id,
            'resident_id' => $morador->id,
            'start_time' => null,
            'end_time' => null,
            'status' => ReservationStatus::Rejected,
            'rejection_reason' => 'O salão está em reforma.',
        ]);

        $linha = $this->relatorio([])->json('data.reservations.data.0');

        $this->assertSame('2026-09-12', $linha['date']);
        $this->assertSame('Recusada', $linha['status_label']);
        $this->assertTrue($linha['full_day']);
        $this->assertSame('O salão está em reforma.', $linha['rejection_reason']);
        $this->assertSame(['id' => $area->id, 'name' => 'Salão de festas'], $linha['common_area']);
        $this->assertSame('Ana Silva', $linha['resident']['name']);
        $this->assertSame(['id' => $unidade->id, 'code' => $unidade->code], $linha['resident']['unit']);
        $this->assertStringNotContainsString('@', json_encode($linha));
    }

    public function test_reserva_com_horario_nao_e_diaria(): void
    {
        $this->reserva('2026-09-12', ['start_time' => '10:00', 'end_time' => '12:00']);

        $this->assertFalse($this->relatorio([])->json('data.reservations.data.0.full_day'));
    }

    public function test_a_exportacao_traz_todas_as_linhas_do_filtro(): void
    {
        $area = $this->area('Churrasqueira');

        foreach (range(1, 25) as $dia) {
            $this->reserva(sprintf('2026-09-%02d', $dia), ['common_area_id' => $area->id]);
        }

        $this->relatorio(['export' => 'true', 'limit' => 10, 'page' => 2])
            ->assertJsonPath('data.reservations.total', 25)
            ->assertJsonPath('data.reservations.per_page', 25)
            ->assertJsonPath('data.reservations.last_page', 1)
            ->assertJsonCount(25, 'data.reservations.data');
    }

    public function test_exportacao_acima_do_teto_pede_um_periodo_menor(): void
    {
        config(['reports.reservations.max_export_rows' => 3]);
        $area = $this->area('Churrasqueira');

        foreach (range(1, 4) as $dia) {
            $this->reserva(sprintf('2026-09-%02d', $dia), ['common_area_id' => $area->id]);
        }

        $this->relatorio(['export' => 'true'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O período tem 4 reservas, acima do máximo de 3 por exportação. Reduza o período ou use os filtros.');
    }

    public function test_periodo_sem_reservas_devolve_relatorio_zerado(): void
    {
        $this->area('Churrasqueira');

        $this->relatorio([])
            ->assertStatus(201)
            ->assertJsonPath('data.summary.total', 0)
            ->assertJsonPath('data.by_area.0.total', 0)
            ->assertJsonPath('data.reservations.data', [])
            ->assertJsonPath('data.reservations.total', 0);
    }
}
