<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
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
 * UC03 / RF03 — solicitação de reserva de área comum.
 *
 * RN01: nada se sobrepõe a uma reserva pendente ou aprovada, e a diária ocupa
 * o dia. RN02: área disponível, dentro do horário, na grade de 30 minutos e
 * até a duração máxima. A reserva é sempre do morador da sessão.
 *
 * Os horários da área e das reservas existentes são gravados como
 * "HH:MM:SS", que é como o MySQL de produção os devolve.
 */
class ReservationCreationTest extends TestCase
{
    use RefreshDatabase;

    /** Uma segunda-feira, 14h10. */
    private const HOJE = '2026-11-23';

    private const AMANHA = '2026-11-24';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::HOJE.' 14:10:00'));
    }

    private function morador(array $attributes = []): User
    {
        $morador = User::factory()->resident()->create($attributes);
        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);

        return $morador;
    }

    /** Das 8h às 22h, até duas horas, sem aprovação. */
    private function area(array $attributes = []): CommonArea
    {
        return CommonArea::factory()->create([
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'max_reservation_minutes' => 120,
            'requires_approval' => false,
            ...$attributes,
        ]);
    }

    private function reservar(User $morador, CommonArea $area, ?string $inicio, ?string $fim, string $data = self::AMANHA, array $extra = []): TestResponse
    {
        return $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $area->id,
            'date' => $data,
            'start_time' => $inicio,
            'end_time' => $fim,
            ...$extra,
        ]);
    }

    private function existente(CommonArea $area, ?string $inicio, ?string $fim, ReservationStatus $status = ReservationStatus::Approved): Reservation
    {
        return Reservation::factory()->create([
            'common_area_id' => $area->id,
            'date' => self::AMANHA,
            'start_time' => $inicio,
            'end_time' => $fim,
            'status' => $status,
        ]);
    }

    public function test_area_sem_aprovacao_gera_reserva_aprovada_do_morador_da_sessao(): void
    {
        $morador = $this->morador();
        $area = $this->area(['name' => 'Quadra']);

        $resposta = $this->reservar($morador, $area, '10:00', '12:00')
            ->assertStatus(201)
            ->assertJsonPath('data.common_area_id', $area->id)
            ->assertJsonPath('data.resident_id', $morador->id)
            ->assertJsonPath('data.date', self::AMANHA)
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.end_time', '12:00')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.common_area', ['id' => $area->id, 'name' => 'Quadra']);

        $id = $resposta->json('data.id');
        $resposta->assertJsonPath('message', "Reserva {$id} da área {$area->id} Quadra para 24/11/2026, das 10:00 às 12:00, aprovada.");

        $this->assertDatabaseHas('reservations', [
            'id' => $id,
            'common_area_id' => $area->id,
            'resident_id' => $morador->id,
            'status' => 'approved',
        ]);
    }

    public function test_area_com_aprovacao_gera_reserva_pendente(): void
    {
        $area = $this->area(['requires_approval' => true]);

        $resposta = $this->reservar($this->morador(), $area, '10:00', '11:00')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertStringEndsWith('solicitada.', $resposta->json('message'));
    }

    public function test_a_reserva_e_registrada_na_auditoria(): void
    {
        $morador = $this->morador();
        $area = $this->area();

        $id = $this->reservar($morador, $area, null, null)->assertStatus(201)->json('data.id');

        $log = Log::where('type_log_id', TypeLogEnum::RESERVATION->value)->sole();

        $this->assertSame($morador->id, $log->user_id);
        $this->assertSame("Reserva {$id} da área {$area->id} {$area->name} para 24/11/2026, dia inteiro, aprovada.", $log->description);
    }

    public function test_quem_reserva_e_sempre_o_morador_da_sessao(): void
    {
        $morador = $this->morador();
        $outro = $this->morador();

        $this->reservar($morador, $this->area(), '10:00', '11:00', extra: ['resident_id' => $outro->id])
            ->assertStatus(201)
            ->assertJsonPath('data.resident_id', $morador->id);

        $this->assertDatabaseMissing('reservations', ['resident_id' => $outro->id]);
    }

    public function test_morador_sem_unidade_vinculada_nao_reserva(): void
    {
        $this->reservar(User::factory()->resident()->create(), $this->area(), '10:00', '11:00')
            ->assertStatus(403)
            ->assertJsonPath('error', 'Seu usuário ainda não está vinculado a uma unidade. Procure a administração do condomínio.');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_so_o_morador_tem_o_caso_de_uso(): void
    {
        $area = $this->area();

        foreach ([User::factory()->employee()->create(), User::factory()->syndic()->create(), User::factory()->admin()->create()] as $usuario) {
            $this->reservar($usuario, $area, '10:00', '11:00')->assertStatus(403);
        }

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_sem_sessao_responde_401(): void
    {
        $this->postJson('/api/reservations', ['common_area_id' => $this->area()->id, 'date' => self::AMANHA])
            ->assertStatus(401);
    }

    public function test_rn01_sobreposicao_com_reserva_pendente_ou_aprovada_e_recusada(): void
    {
        $area = $this->area();
        $morador = $this->morador();
        $this->existente($area, '10:00:00', '12:00:00');
        $this->existente($area, '14:00:00', '15:00:00', ReservationStatus::Pending);

        foreach ([['09:00', '10:30'], ['11:30', '13:00'], ['10:30', '11:00'], ['14:30', '15:30'], ['09:00', '11:00']] as [$inicio, $fim]) {
            $this->reservar($morador, $area, $inicio, $fim)
                ->assertStatus(409)
                ->assertJsonPath('message', 'Já existe uma reserva para esta área, data e horário.');
        }

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_rn01_reservas_encostadas_nao_conflitam(): void
    {
        $area = $this->area();
        $morador = $this->morador();
        $this->existente($area, '10:00:00', '12:00:00');

        $this->reservar($morador, $area, '08:00', '10:00')->assertStatus(201);
        $this->reservar($morador, $area, '12:00', '14:00')->assertStatus(201);
    }

    public function test_rn08_cancelada_e_recusada_nao_bloqueiam(): void
    {
        $area = $this->area();
        $this->existente($area, '10:00:00', '12:00:00', ReservationStatus::Cancelled);
        $this->existente($area, null, null, ReservationStatus::Rejected);

        $this->reservar($this->morador(), $area, '10:00', '12:00')->assertStatus(201);
    }

    public function test_rn01_diaria_conflita_nos_dois_sentidos(): void
    {
        $morador = $this->morador();

        $comHorario = $this->area();
        $this->existente($comHorario, '20:00:00', '21:00:00');
        $this->reservar($morador, $comHorario, null, null)->assertStatus(409);

        $comDiaria = $this->area();
        $this->existente($comDiaria, null, null);
        $this->reservar($morador, $comDiaria, '08:00', '09:00')->assertStatus(409);
    }

    public function test_a_diaria_nao_tem_limite_de_duracao(): void
    {
        $this->reservar($this->morador(), $this->area(['max_reservation_minutes' => 60]), null, null)
            ->assertStatus(201)
            ->assertJsonPath('data.start_time', null)
            ->assertJsonPath('data.end_time', null);
    }

    public function test_rn02_area_indisponivel_nao_aceita_reserva(): void
    {
        $this->reservar($this->morador(), CommonArea::factory()->unavailable()->create(), '10:00', '11:00')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta área comum não está disponível para reserva.');
    }

    public function test_rn02_area_sem_horario_cadastrado_nao_aceita_reserva(): void
    {
        $area = $this->area(['opening_time' => null, 'closing_time' => null]);

        foreach ([['10:00', '11:00'], [null, null]] as [$inicio, $fim]) {
            $this->reservar($this->morador(), $area, $inicio, $fim)
                ->assertStatus(422)
                ->assertJsonPath('message', 'Esta área comum não tem horário de funcionamento cadastrado. Procure a administração.');
        }
    }

    public function test_rn02_fora_do_horario_de_funcionamento(): void
    {
        $area = $this->area();
        $morador = $this->morador();

        $this->reservar($morador, $area, '07:30', '08:30')
            ->assertStatus(422)
            ->assertJsonPath('message', 'A área abre às 08:00.');

        $this->reservar($morador, $area, '21:30', '22:30')
            ->assertStatus(422)
            ->assertJsonPath('message', 'A área fecha às 22:00.');

        // As pontas exatas valem, mesmo com o horário gravado como "08:00:00".
        $this->reservar($morador, $area, '08:00', '09:00')->assertStatus(201);
        $this->reservar($morador, $area, '21:00', '22:00')->assertStatus(201);
    }

    public function test_rn02_duracao_maxima_da_area(): void
    {
        $area = $this->area(['max_reservation_minutes' => 90]);
        $morador = $this->morador();

        $this->reservar($morador, $area, '10:00', '12:00')
            ->assertStatus(422)
            ->assertJsonPath('message', 'A duração máxima desta área é de 90 minutos.');

        $this->reservar($morador, $area, '10:00', '11:30')->assertStatus(201);
    }

    public function test_rn02_area_sem_limite_usa_duas_horas(): void
    {
        $area = $this->area(['max_reservation_minutes' => null]);
        $morador = $this->morador();

        $this->reservar($morador, $area, '10:00', '12:30')
            ->assertStatus(422)
            ->assertJsonPath('message', 'A duração máxima desta área é de 120 minutos.');

        $this->reservar($morador, $area, '10:00', '12:00')->assertStatus(201);

        // A disponibilidade informa o mesmo limite que a criação aplica.
        $this->actingAs($morador)
            ->getJson("/api/common-areas/{$area->id}/availability?start_date=".self::AMANHA.'&end_date='.self::AMANHA)
            ->assertJsonPath('data.common_area.max_reservation_minutes', 120);
    }

    public function test_rn02_horarios_fora_da_grade_de_30_minutos(): void
    {
        $area = $this->area();
        $morador = $this->morador();

        foreach ([['10:15', '11:00'], ['10:00', '10:45'], ['10:10', '10:40']] as [$inicio, $fim]) {
            $this->reservar($morador, $area, $inicio, $fim)
                ->assertStatus(422)
                ->assertJsonPath('message', 'Escolha horários em blocos de 30 minutos a partir da abertura, às 08:00.');
        }

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_a_grade_conta_da_abertura_e_aceita_terminar_no_fechamento(): void
    {
        // Abre 8h15 e fecha 21h50: os blocos são 8h15, 8h45... e o último
        // termina no fechamento, fora da grade.
        $area = $this->area(['opening_time' => '08:15:00', 'closing_time' => '21:50:00']);
        $morador = $this->morador();

        $this->reservar($morador, $area, '08:00', '09:00')->assertStatus(422);
        $this->reservar($morador, $area, '09:00', '10:00')->assertStatus(422);
        $this->reservar($morador, $area, '08:15', '09:45')->assertStatus(201);
        $this->reservar($morador, $area, '20:45', '21:50')->assertStatus(201);
    }

    public function test_so_um_dos_horarios_ou_intervalo_invertido(): void
    {
        $area = $this->area();
        $morador = $this->morador();

        $this->reservar($morador, $area, '10:00', null)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe os dois horários ou deixe ambos vazios para reservar o dia inteiro.');

        $this->reservar($morador, $area, null, '11:00')->assertStatus(422);

        $this->reservar($morador, $area, '11:00', '10:00')
            ->assertStatus(422)
            ->assertJsonPath('message', 'O horário final deve ser maior que o inicial.');

        $this->reservar($morador, $area, '10:00', '10:00')->assertStatus(422);
    }

    public function test_data_passada_e_horario_de_hoje_que_ja_comecou(): void
    {
        $area = $this->area();
        $morador = $this->morador();

        $this->reservar($morador, $area, '10:00', '11:00', '2026-11-22')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível reservar uma data passada.');

        // 14h10: o bloco das 14h já começou.
        $this->reservar($morador, $area, '14:00', '15:00', self::HOJE)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível reservar um horário que já passou.');

        $this->reservar($morador, $area, '14:30', '15:30', self::HOJE)->assertStatus(201);
    }

    public function test_validacao_do_formulario(): void
    {
        $morador = $this->morador();

        $this->actingAs($morador)->postJson('/api/reservations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['common_area_id' => 'Selecione uma área comum.', 'date' => 'Informe a data da reserva.']);

        $this->actingAs($morador)->postJson('/api/reservations', ['common_area_id' => 999, 'date' => self::AMANHA])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['common_area_id' => 'A área comum informada não existe.']);

        $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $this->area()->id, 'date' => '24/11/2026', 'start_time' => '10h', 'end_time' => '11:00:00',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'start_time', 'end_time']);
    }

    public function test_o_que_foi_reservado_some_da_disponibilidade(): void
    {
        $area = $this->area(['closing_time' => '12:00:00']);
        $morador = $this->morador();

        $this->reservar($morador, $area, '08:00', '10:00')->assertStatus(201);

        $dia = $this->actingAs($morador)
            ->getJson("/api/common-areas/{$area->id}/availability?start_date=".self::AMANHA.'&end_date='.self::AMANHA)
            ->json('data.days.0');

        $this->assertSame('partial', $dia['status']);
        $this->assertSame([['start_time' => '10:00', 'end_time' => '12:00', 'minutes' => 120]], $dia['free_periods']);
    }
}
