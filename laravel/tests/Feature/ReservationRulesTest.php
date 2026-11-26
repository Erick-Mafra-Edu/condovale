<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Actions\ReviewReservationAction;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * UC03 / RF03 / RN02 — "toda reserva deverá respeitar a disponibilidade, os
 * horários permitidos e as regras de utilização definidas para a área comum".
 *
 * As regras em si estão cobertas em ReservationCreationTest. Aqui fica o que
 * este card acrescentou: cada recusa diz o campo violado (RSV-04 do plano de
 * testes), e a aprovação confere as regras de novo, com a área como ela está
 * agora — não como estava quando o morador pediu.
 */
class ReservationRulesTest extends TestCase
{
    use RefreshDatabase;

    /** Uma terça-feira, 9h. */
    private const HOJE = '2026-11-24';

    private const AMANHA = '2026-11-25';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::HOJE.' 09:00:00'));
    }

    private function area(array $attributes = []): CommonArea
    {
        return CommonArea::factory()->create([
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'max_reservation_minutes' => 120,
            'requires_approval' => true,
            ...$attributes,
        ]);
    }

    private function morador(): User
    {
        $morador = User::factory()->resident()->create();
        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);

        return $morador;
    }

    /**
     * Quem analisa é sempre a administração logada: é ela que assina o
     * registro de auditoria da decisão.
     */
    private function comoAdministracao(): void
    {
        $this->actingAs(User::factory()->admin()->create());
    }

    /** Pedido das 10h às 12h de amanhã, gravado como o MySQL devolve. */
    private function pendente(CommonArea $area, array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'common_area_id' => $area->id,
            'date' => self::AMANHA,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => ReservationStatus::Pending,
            ...$attributes,
        ]);
    }

    public function test_cada_recusa_diz_o_campo_violado(): void
    {
        $morador = $this->morador();
        $area = $this->area();
        $hoje = self::HOJE;
        $amanha = self::AMANHA;

        $casos = [
            'data passada' => [['date' => '2026-11-23', 'start_time' => '10:00', 'end_time' => '11:00'], 'date', 'Não é possível reservar uma data passada.'],
            'só o início' => [['date' => $amanha, 'start_time' => '10:00', 'end_time' => null], 'end_time', 'Informe os dois horários ou deixe ambos vazios para reservar o dia inteiro.'],
            'só o fim' => [['date' => $amanha, 'start_time' => null, 'end_time' => '11:00'], 'start_time', 'Informe os dois horários ou deixe ambos vazios para reservar o dia inteiro.'],
            'fim antes do início' => [['date' => $amanha, 'start_time' => '11:00', 'end_time' => '10:00'], 'end_time', 'O horário final deve ser maior que o inicial.'],
            'antes da abertura' => [['date' => $amanha, 'start_time' => '07:00', 'end_time' => '08:30'], 'start_time', 'A área abre às 08:00.'],
            'depois do fechamento' => [['date' => $amanha, 'start_time' => '21:30', 'end_time' => '22:30'], 'end_time', 'A área fecha às 22:00.'],
            'início fora da grade' => [['date' => $amanha, 'start_time' => '10:15', 'end_time' => '11:00'], 'start_time', 'Escolha horários em blocos de 30 minutos a partir da abertura, às 08:00.'],
            'fim fora da grade' => [['date' => $amanha, 'start_time' => '10:00', 'end_time' => '10:45'], 'end_time', 'Escolha horários em blocos de 30 minutos a partir da abertura, às 08:00.'],
            'acima da duração' => [['date' => $amanha, 'start_time' => '10:00', 'end_time' => '12:30'], 'end_time', 'A duração máxima desta área é de 120 minutos.'],
            'horário de hoje que já começou' => [['date' => $hoje, 'start_time' => '08:30', 'end_time' => '09:30'], 'start_time', 'Não é possível reservar um horário que já passou.'],
        ];

        foreach ($casos as [$periodo, $campo, $mensagem]) {
            $this->actingAs($morador)->postJson('/api/reservations', ['common_area_id' => $area->id, ...$periodo])
                ->assertStatus(422)
                ->assertJsonPath('message', $mensagem)
                ->assertJsonPath("errors.{$campo}", [$mensagem])
                ->assertJsonCount(1, 'errors');
        }

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_a_area_fora_de_uso_aponta_o_campo_da_area(): void
    {
        $morador = $this->morador();

        foreach ([
            [$this->area(['status' => 'unavailable']), 'Esta área comum não está disponível para reserva.'],
            [$this->area(['opening_time' => null, 'closing_time' => null]), 'Esta área comum não tem horário de funcionamento cadastrado. Procure a administração.'],
        ] as [$area, $mensagem]) {
            $this->actingAs($morador)->postJson('/api/reservations', [
                'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => '10:00', 'end_time' => '11:00',
            ])
                ->assertStatus(422)
                ->assertJsonPath('errors.common_area_id', [$mensagem]);
        }
    }

    public function test_conflito_nao_e_erro_de_campo(): void
    {
        $area = $this->area(['requires_approval' => false]);
        $this->pendente($area);

        $resposta = $this->actingAs($this->morador())->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => '10:00', 'end_time' => '11:00',
        ])->assertStatus(409);

        // O formulário está certo; o horário é que foi tomado.
        $this->assertArrayNotHasKey('errors', $resposta->json());
    }

    public function test_aprovar_quando_as_regras_ainda_valem(): void
    {
        $pedido = $this->pendente($this->area());
        $this->comoAdministracao();

        $this->assertSame(ReservationStatus::Approved, ReviewReservationAction::execute($pedido, ReservationStatus::Approved)->status);
    }

    public function test_aprovar_confere_a_area_como_ela_esta_agora(): void
    {
        $casos = [
            'ficou indisponível' => [['status' => 'unavailable'], 'common_area_id', 'esta área comum não está disponível para reserva.'],
            'passou a abrir mais tarde' => [['opening_time' => '11:00:00'], 'start_time', 'a área abre às 11:00.'],
            'passou a fechar mais cedo' => [['closing_time' => '11:00:00'], 'end_time', 'a área fecha às 11:00.'],
            'reduziu a duração' => [['max_reservation_minutes' => 60], 'end_time', 'a duração máxima desta área é de 60 minutos.'],
            'mudou a grade' => [['opening_time' => '08:15:00'], 'start_time', 'escolha horários em blocos de 30 minutos a partir da abertura, às 08:15.'],
        ];
        $this->comoAdministracao();

        foreach ($casos as $caso => [$mudanca, $campo, $motivo]) {
            $area = $this->area();
            $pedido = $this->pendente($area);
            $area->update($mudanca);

            try {
                ReviewReservationAction::execute($pedido, ReservationStatus::Approved);
                $this->fail("Aprovar depois que a área {$caso} deveria ser recusado.");
            } catch (BusinessRuleException $e) {
                $this->assertSame(422, $e->getCode(), $caso);
                $this->assertSame("Esta solicitação não pode mais ser aprovada: {$motivo} Reprove-a informando o motivo.", $e->getMessage(), $caso);
                $this->assertSame($campo, $e->field(), $caso);
            }

            $this->assertSame(ReservationStatus::Pending, $pedido->refresh()->status, $caso);
        }
    }

    public function test_aprovar_um_pedido_cuja_data_ou_horario_ja_passou(): void
    {
        $area = $this->area();
        $ontem = $this->pendente($area, ['date' => '2026-11-22']);
        $hoje = $this->pendente($area, ['date' => self::HOJE, 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $this->comoAdministracao();

        foreach ([[$ontem, 'date', 'não é possível reservar uma data passada.'], [$hoje, 'start_time', 'não é possível reservar um horário que já passou.']] as [$pedido, $campo, $motivo]) {
            try {
                ReviewReservationAction::execute($pedido, ReservationStatus::Approved);
                $this->fail('Aprovar um pedido que já passou deveria ser recusado.');
            } catch (BusinessRuleException $e) {
                $this->assertSame("Esta solicitação não pode mais ser aprovada: {$motivo} Reprove-a informando o motivo.", $e->getMessage());
                $this->assertSame($campo, $e->field());
            }
        }
    }

    public function test_reprovar_continua_possivel_quando_nao_cabe_mais(): void
    {
        $area = $this->area();
        $pedido = $this->pendente($area);
        $area->update(['status' => 'unavailable']);
        $this->comoAdministracao();

        $reprovado = ReviewReservationAction::execute($pedido, ReservationStatus::Rejected, 'Área em manutenção.');

        $this->assertSame(ReservationStatus::Rejected, $reprovado->status);
        $this->assertSame('Área em manutenção.', $reprovado->rejection_reason);
    }

    public function test_a_recusa_da_aprovacao_chega_ao_cliente_com_o_campo(): void
    {
        $area = $this->area();
        $pedido = $this->pendente($area);
        $area->update(['max_reservation_minutes' => 60]);
        $this->comoAdministracao();

        try {
            ReviewReservationAction::execute($pedido, ReservationStatus::Approved);
            $this->fail('A aprovação deveria ser recusada.');
        } catch (BusinessRuleException $e) {
            $corpo = MessageService::throwable($e)->getData(true);
        }

        $this->assertSame(422, $corpo['code']);
        $this->assertSame([$corpo['message']], $corpo['errors']['end_time']);
    }
}
