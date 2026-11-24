<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Actions\CancelReservationAction;
use App\Http\Actions\ReviewReservationAction;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * UC03 / RF03 / RN01 — duas reservas não ocupam o mesmo local, data e horário.
 *
 * As formas de sobreposição na criação estão em ReservationCreationTest; aqui
 * fica o pedido repetido, o que não é conflito, o período liberado pelo
 * cancelamento e a aprovação, que é a outra porta por onde duas reservas
 * poderiam acabar valendo para o mesmo horário. A disputa entre pedidos
 * simultâneos está em ReservationRaceTest, que roda contra MySQL.
 */
class ReservationConflictTest extends TestCase
{
    use RefreshDatabase;

    private const AMANHA = '2026-11-25';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-24 09:00:00'));
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
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'max_reservation_minutes' => 240,
            'requires_approval' => false,
            ...$attributes,
        ]);
    }

    private function reservar(User $morador, CommonArea $area, ?string $inicio, ?string $fim, string $data = self::AMANHA): TestResponse
    {
        return $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => $data, 'start_time' => $inicio, 'end_time' => $fim,
        ]);
    }

    /** Gravada direto, como o MySQL devolve ("HH:MM:SS"), sem passar pela regra. */
    private function gravada(CommonArea $area, ?string $inicio, ?string $fim, ReservationStatus $status): Reservation
    {
        return Reservation::factory()->create([
            'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => $inicio, 'end_time' => $fim, 'status' => $status,
        ]);
    }

    public function test_o_mesmo_local_data_e_horario_nao_e_reservado_duas_vezes(): void
    {
        $area = $this->area();
        $primeiro = $this->morador();

        $this->reservar($primeiro, $area, '10:00', '12:00')->assertStatus(201);

        // Nem por outro morador, nem pelo mesmo de novo (o clique duplo).
        foreach ([$this->morador(), $primeiro] as $morador) {
            $this->reservar($morador, $area, '10:00', '12:00')
                ->assertStatus(409)
                ->assertJsonPath('message', 'Já existe uma reserva para esta área, data e horário.');
        }

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_outra_area_ou_outra_data_nao_e_conflito(): void
    {
        $area = $this->area();
        $morador = $this->morador();

        $this->reservar($morador, $area, '10:00', '12:00')->assertStatus(201);
        $this->reservar($morador, $this->area(), '10:00', '12:00')->assertStatus(201);
        $this->reservar($morador, $area, '10:00', '12:00', '2026-11-26')->assertStatus(201);

        $this->assertDatabaseCount('reservations', 3);
    }

    public function test_cancelar_libera_o_periodo_para_outro_morador(): void
    {
        $area = $this->area();
        $primeiro = $this->morador();
        $segundo = $this->morador();

        $id = $this->reservar($primeiro, $area, '10:00', '12:00')->json('data.id');
        $this->reservar($segundo, $area, '10:00', '12:00')->assertStatus(409);

        CancelReservationAction::execute(Reservation::find($id), $primeiro);

        $this->reservar($segundo, $area, '10:00', '12:00')->assertStatus(201);
    }

    public function test_pedido_pendente_ja_segura_o_periodo(): void
    {
        $area = $this->area(['requires_approval' => true]);

        $this->reservar($this->morador(), $area, '10:00', '12:00')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->reservar($this->morador(), $area, '11:00', '12:00')->assertStatus(409);
    }

    public function test_aprovar_nao_passa_por_cima_de_outra_reserva(): void
    {
        $area = $this->area(['requires_approval' => true]);
        // Duas sobrepostas, como só um dado antigo ou carregado à mão deixaria.
        $this->gravada($area, '10:00:00', '12:00:00', ReservationStatus::Approved);
        $pendente = $this->gravada($area, '11:00:00', '13:00:00', ReservationStatus::Pending);

        try {
            ReviewReservationAction::execute($pendente, ReservationStatus::Approved);
            $this->fail('Aprovar sobre outra reserva deveria dar conflito.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('Já existe uma reserva para esta área, data e horário.', $e->getMessage());
        }

        $this->assertSame(ReservationStatus::Pending, $pendente->refresh()->status);
    }

    public function test_aprovar_encostada_e_aprovar_sozinha_passam(): void
    {
        $area = $this->area(['requires_approval' => true]);
        $this->gravada($area, '10:00:00', '12:00:00', ReservationStatus::Approved);
        $encostada = $this->gravada($area, '12:00:00', '14:00:00', ReservationStatus::Pending);
        // A própria reserva não conta como conflito consigo mesma.
        $diaria = Reservation::factory()->create([
            'common_area_id' => $area->id, 'date' => '2026-11-26', 'start_time' => null, 'end_time' => null, 'status' => ReservationStatus::Pending,
        ]);

        $this->assertSame(ReservationStatus::Approved, ReviewReservationAction::execute($encostada, ReservationStatus::Approved)->status);
        $this->assertSame(ReservationStatus::Approved, ReviewReservationAction::execute($diaria, ReservationStatus::Approved)->status);
    }

    public function test_reprovar_nao_depende_de_conflito(): void
    {
        $area = $this->area(['requires_approval' => true]);
        $this->gravada($area, null, null, ReservationStatus::Approved);
        $pendente = $this->gravada($area, '10:00:00', '12:00:00', ReservationStatus::Pending);

        $reprovada = ReviewReservationAction::execute($pendente, ReservationStatus::Rejected, 'Área reservada para o condomínio.');

        $this->assertSame(ReservationStatus::Rejected, $reprovada->status);
        $this->assertSame('Área reservada para o condomínio.', $reprovada->rejection_reason);
    }
}
