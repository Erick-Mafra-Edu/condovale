<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Http\Actions\CancelReservationAction;
use App\Http\Actions\ReviewReservationAction;
use App\Models\CommonArea;
use App\Models\Log;
use App\Models\Reservation;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * UC03 / RF03 / RN08 — "o cancelamento de uma reserva deverá liberar
 * novamente o respectivo período para novas reservas".
 *
 * O morador cancela as próprias reservas; a administração, qualquer uma. Os
 * horários são gravados como "HH:MM:SS", como o MySQL de produção devolve.
 */
class ReservationCancellationTest extends TestCase
{
    use RefreshDatabase;

    /** Uma terça-feira, 14h10. */
    private const HOJE = '2026-11-24';

    private const AMANHA = '2026-11-25';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::HOJE.' 14:10:00'));
    }

    private function morador(): User
    {
        $morador = User::factory()->resident()->create();
        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);

        return $morador;
    }

    private function area(array $attributes = []): CommonArea
    {
        return CommonArea::factory()->create([
            'name' => 'Quadra',
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'max_reservation_minutes' => 120,
            'requires_approval' => false,
            ...$attributes,
        ]);
    }

    private function reserva(User $morador, CommonArea $area, array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'common_area_id' => $area->id,
            'resident_id' => $morador->id,
            'date' => self::AMANHA,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => ReservationStatus::Approved,
            ...$attributes,
        ]);
    }

    private function cancelar(User $usuario, Reservation $reserva): TestResponse
    {
        return $this->actingAs($usuario)->postJson("/api/reservations/{$reserva->id}/cancel");
    }

    public function test_o_morador_cancela_a_propria_reserva(): void
    {
        $morador = $this->morador();
        $area = $this->area();
        $reserva = $this->reserva($morador, $area);

        $this->cancelar($morador, $reserva)
            ->assertStatus(201)
            ->assertJsonPath('data.id', $reserva->id)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.common_area', ['id' => $area->id, 'name' => 'Quadra'])
            ->assertJsonPath(
                'message',
                "Reserva {$reserva->id} da área {$area->id} Quadra para 25/11/2026, das 10:00 às 12:00, cancelada. O período voltou a ficar disponível."
            );

        $log = Log::where('type_log_id', TypeLogEnum::RESERVATION->value)->sole();
        $this->assertSame($morador->id, $log->user_id);
        $this->assertSame("Reserva {$reserva->id} da área {$area->id} Quadra para 25/11/2026, das 10:00 às 12:00, cancelada.", $log->description);
    }

    public function test_o_morador_desiste_de_um_pedido_pendente(): void
    {
        $morador = $this->morador();
        $reserva = $this->reserva($morador, $this->area(), ['status' => ReservationStatus::Pending]);

        $this->cancelar($morador, $reserva)->assertStatus(201)->assertJsonPath('data.status', 'cancelled');
    }

    public function test_rn08_o_periodo_cancelado_volta_para_a_agenda_e_pode_ser_reservado(): void
    {
        $area = $this->area();
        $primeiro = $this->morador();
        $segundo = $this->morador();
        $reserva = $this->reserva($primeiro, $area);
        $disponibilidade = fn () => $this->actingAs($segundo)
            ->getJson("/api/common-areas/{$area->id}/availability?start_date=".self::AMANHA.'&end_date='.self::AMANHA)
            ->json('data.days.0');
        $pedir = fn () => $this->actingAs($segundo)->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => '10:00', 'end_time' => '12:00',
        ]);

        $this->assertSame('partial', $disponibilidade()['status']);
        $pedir()->assertStatus(409);

        $this->cancelar($primeiro, $reserva)->assertStatus(201);

        $dia = $disponibilidade();
        $this->assertSame('available', $dia['status']);
        $this->assertTrue($dia['full_day_available']);
        $this->assertSame([], $dia['occupied_periods']);
        $pedir()->assertStatus(201);
    }

    public function test_a_diaria_cancelada_libera_o_dia_inteiro(): void
    {
        $morador = $this->morador();
        $area = $this->area();
        $diaria = $this->reserva($morador, $area, ['start_time' => null, 'end_time' => null]);

        $this->cancelar($morador, $diaria)
            ->assertStatus(201)
            ->assertJsonPath('message', "Reserva {$diaria->id} da área {$area->id} Quadra para 25/11/2026, dia inteiro, cancelada. O período voltou a ficar disponível.");

        $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => '08:00', 'end_time' => '10:00',
        ])->assertStatus(201);
    }

    public function test_o_morador_nao_cancela_a_reserva_de_outro(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area());

        $this->cancelar($this->morador(), $reserva)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você só pode cancelar as suas próprias reservas.');

        $this->assertSame(ReservationStatus::Approved, $reserva->refresh()->status);
        $this->assertSame(0, Log::count());
    }

    public function test_a_administracao_cancela_qualquer_reserva(): void
    {
        $area = $this->area();
        $reserva = $this->reserva($this->morador(), $area);
        $admin = User::factory()->admin()->create();

        $this->cancelar($admin, $reserva)->assertStatus(201)->assertJsonPath('data.status', 'cancelled');

        $log = Log::where('type_log_id', TypeLogEnum::RESERVATION->value)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringEndsWith('cancelada pela administração.', $log->description);
    }

    public function test_cancelada_ou_reprovada_nao_se_cancela_de_novo(): void
    {
        $morador = $this->morador();
        $area = $this->area();

        $this->cancelar($morador, $this->reserva($morador, $area, ['status' => ReservationStatus::Cancelled]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Esta reserva já está cancelada.');

        $this->cancelar($morador, $this->reserva($morador, $area, ['status' => ReservationStatus::Rejected]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Uma reserva reprovada não pode ser cancelada.');
    }

    public function test_reserva_que_ja_terminou_fica_no_historico(): void
    {
        $morador = $this->morador();
        $area = $this->area();

        // Ontem, e hoje das 10h às 12h (agora são 14h10).
        foreach ([['date' => '2026-11-23'], ['date' => self::HOJE]] as $quando) {
            $reserva = $this->reserva($morador, $area, $quando);

            $this->cancelar($morador, $reserva)
                ->assertStatus(409)
                ->assertJsonPath('message', 'Esta reserva já terminou e não pode mais ser cancelada.');

            $this->assertSame(ReservationStatus::Approved, $reserva->refresh()->status);
        }
    }

    public function test_reserva_em_andamento_e_diaria_de_hoje_ainda_se_cancelam(): void
    {
        $morador = $this->morador();
        $area = $this->area();

        // Das 14h às 16h: começou, mas não acabou; o resto do período é liberado.
        $emAndamento = $this->reserva($morador, $area, ['date' => self::HOJE, 'start_time' => '14:00:00', 'end_time' => '16:00:00']);
        $diaria = $this->reserva($morador, $this->area(['name' => 'Salão']), ['date' => self::HOJE, 'start_time' => null, 'end_time' => null]);

        $this->cancelar($morador, $emAndamento)->assertStatus(201);
        $this->cancelar($morador, $diaria)->assertStatus(201);
    }

    public function test_quem_perdeu_o_vinculo_ainda_cancela_a_propria_reserva(): void
    {
        $morador = User::factory()->resident()->create();
        UnitOccupancy::factory()->ended()->create(['user_id' => $morador->id]);
        $reserva = $this->reserva($morador, $this->area());

        $this->cancelar($morador, $reserva)->assertStatus(201);
    }

    public function test_sindico_e_funcionario_nao_cancelam(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area());

        $this->cancelar(User::factory()->syndic()->create(), $reserva)->assertStatus(403);
        $this->cancelar(User::factory()->employee()->create(), $reserva)->assertStatus(403);

        $this->assertSame(ReservationStatus::Approved, $reserva->refresh()->status);
    }

    public function test_sem_sessao_e_reserva_inexistente(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area());

        $this->postJson("/api/reservations/{$reserva->id}/cancel")->assertStatus(401);

        $this->actingAs($this->morador())->postJson('/api/reservations/999/cancel')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Reserva 999 não encontrada.');
    }

    public function test_aprovar_um_pedido_ja_cancelado_e_recusado_mesmo_com_dado_antigo(): void
    {
        $morador = $this->morador();
        $pedido = $this->reserva($morador, $this->area(['requires_approval' => true]), ['status' => ReservationStatus::Pending]);

        // A tela da administração carregou o pedido ainda pendente...
        $carregadoAntes = Reservation::find($pedido->id);

        // ...e o morador cancelou antes de ela decidir.
        $this->cancelar($morador, $pedido)->assertStatus(201);

        try {
            ReviewReservationAction::execute($carregadoAntes, ReservationStatus::Approved);
            $this->fail('A aprovação não pode passar por cima do cancelamento.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('Somente solicitações pendentes podem ser analisadas.', $e->getMessage());
        }

        $this->assertSame(ReservationStatus::Cancelled, $pedido->refresh()->status);
    }

    public function test_cancelar_depois_de_aprovado_libera_mesmo_com_dado_antigo(): void
    {
        $morador = $this->morador();
        $pedido = $this->reserva($morador, $this->area(['requires_approval' => true]), ['status' => ReservationStatus::Pending]);
        $carregadoAntes = Reservation::find($pedido->id);

        ReviewReservationAction::execute(Reservation::find($pedido->id), ReservationStatus::Approved);

        $this->actingAs($morador);
        CancelReservationAction::execute($carregadoAntes, $morador);

        $this->assertSame(ReservationStatus::Cancelled, $pedido->refresh()->status);
    }
}
