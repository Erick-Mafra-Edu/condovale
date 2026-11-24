<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Actions\CreateReservationAction;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * UC03 / RF03 — consulta de disponibilidade de uma área comum.
 *
 * RN01: reserva pendente ou aprovada ocupa o período, e a diária ocupa o dia.
 * RN02: área disponível, dentro do horário de funcionamento, em blocos de 30
 * minutos. RN14: a consulta não diz quem reservou.
 *
 * Os horários das áreas e reservas são gravados como "HH:MM:SS", que é como o
 * MySQL de produção os devolve; a API recebe "HH:MM".
 */
class CommonAreaAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /** Uma segunda-feira, 14h10: hoje tem blocos passados e blocos futuros. */
    private const HOJE = '2026-11-23';

    private const AMANHA = '2026-11-24';

    private const ONTEM = '2026-11-22';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::HOJE.' 14:10:00'));
    }

    /** Das 8h ao meio-dia: oito blocos, fáceis de conferir um a um. */
    private function area(array $attributes = []): CommonArea
    {
        return CommonArea::factory()->create([
            'opening_time' => '08:00:00',
            'closing_time' => '12:00:00',
            'max_reservation_minutes' => 120,
            ...$attributes,
        ]);
    }

    private function reservar(
        CommonArea $area,
        string $date,
        ?string $start,
        ?string $end,
        ReservationStatus $status = ReservationStatus::Approved,
        array $attributes = [],
    ): Reservation {
        return Reservation::factory()->create([
            'common_area_id' => $area->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
            ...$attributes,
        ]);
    }

    private function consultar(CommonArea $area, string $inicio, string $fim, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? User::factory()->resident()->create())
            ->getJson("/api/common-areas/{$area->id}/availability?start_date={$inicio}&end_date={$fim}");
    }

    private function dia(TestResponse $resposta, string $date): array
    {
        return collect($resposta->json('data.days'))->firstWhere('date', $date);
    }

    /** @return list<string> */
    private function estados(array $dia): array
    {
        return array_column($dia['slots'], 'status');
    }

    public function test_dia_sem_reserva_esta_disponivel_com_todos_os_blocos_livres(): void
    {
        $area = $this->area();

        $resposta = $this->consultar($area, self::AMANHA, self::AMANHA)
            ->assertStatus(201)
            ->assertJsonPath('data.slot_minutes', 30)
            ->assertJsonPath('data.common_area.id', $area->id)
            ->assertJsonPath('data.common_area.opening_time', '08:00')
            ->assertJsonPath('data.common_area.closing_time', '12:00')
            ->assertJsonPath('data.common_area.max_reservation_minutes', 120);

        $dia = $this->dia($resposta, self::AMANHA);

        $this->assertSame('available', $dia['status']);
        $this->assertSame('Disponível', $dia['label']);
        $this->assertNull($dia['reason']);
        $this->assertTrue($dia['full_day_available']);
        $this->assertSame([], $dia['occupied_periods']);
        $this->assertSame([['start_time' => '08:00', 'end_time' => '12:00', 'minutes' => 240]], $dia['free_periods']);
        $this->assertCount(8, $dia['slots']);
        $this->assertSame(['start_time' => '08:00', 'end_time' => '08:30', 'status' => 'available'], $dia['slots'][0]);
        $this->assertSame(['start_time' => '11:30', 'end_time' => '12:00', 'status' => 'available'], $dia['slots'][7]);
    }

    public function test_reservas_pendentes_e_aprovadas_ocupam_so_o_seu_periodo(): void
    {
        $area = $this->area();
        $this->reservar($area, self::AMANHA, '09:00:00', '10:00:00');
        $this->reservar($area, self::AMANHA, '11:00:00', '11:30:00', ReservationStatus::Pending);

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA)->assertStatus(201), self::AMANHA);

        $this->assertSame('partial', $dia['status']);
        $this->assertSame('Parcialmente ocupado', $dia['label']);
        $this->assertFalse($dia['full_day_available']);
        $this->assertSame([
            ['start_time' => '09:00', 'end_time' => '10:00'],
            ['start_time' => '11:00', 'end_time' => '11:30'],
        ], $dia['occupied_periods']);
        $this->assertSame([
            ['start_time' => '08:00', 'end_time' => '09:00', 'minutes' => 60],
            ['start_time' => '10:00', 'end_time' => '11:00', 'minutes' => 60],
            ['start_time' => '11:30', 'end_time' => '12:00', 'minutes' => 30],
        ], $dia['free_periods']);
        $this->assertSame(
            ['available', 'available', 'occupied', 'occupied', 'available', 'available', 'occupied', 'available'],
            $this->estados($dia)
        );
    }

    public function test_reserva_fora_da_grade_ocupa_todo_bloco_que_ela_toca(): void
    {
        $area = $this->area();
        $this->reservar($area, self::AMANHA, '09:15:00', '10:15:00');

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA), self::AMANHA);

        $this->assertSame(
            ['available', 'available', 'occupied', 'occupied', 'occupied', 'available', 'available', 'available'],
            $this->estados($dia)
        );
    }

    public function test_cancelada_e_recusada_liberam_o_periodo(): void
    {
        $area = $this->area();
        $this->reservar($area, self::AMANHA, '08:00:00', '10:00:00', ReservationStatus::Cancelled);
        $this->reservar($area, self::AMANHA, null, null, ReservationStatus::Rejected);

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA), self::AMANHA);

        $this->assertSame('available', $dia['status']);
        $this->assertTrue($dia['full_day_available']);
        $this->assertSame([], $dia['occupied_periods']);
    }

    public function test_a_diaria_ocupa_o_dia_inteiro(): void
    {
        $area = $this->area();
        $this->reservar($area, self::AMANHA, null, null);

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA), self::AMANHA);

        $this->assertSame('occupied', $dia['status']);
        $this->assertSame('Ocupado', $dia['label']);
        $this->assertFalse($dia['full_day_available']);
        $this->assertSame([['start_time' => null, 'end_time' => null]], $dia['occupied_periods']);
        $this->assertSame([], $dia['free_periods']);
        $this->assertSame(array_fill(0, 8, 'occupied'), $this->estados($dia));
    }

    public function test_reservas_por_horario_que_cobrem_tudo_ocupam_o_dia(): void
    {
        $area = $this->area();
        $this->reservar($area, self::AMANHA, '08:00:00', '10:00:00');
        $this->reservar($area, self::AMANHA, '10:00:00', '12:00:00');

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA), self::AMANHA);

        $this->assertSame('occupied', $dia['status']);
        $this->assertFalse($dia['full_day_available']);
        $this->assertSame([], $dia['free_periods']);
    }

    public function test_data_passada_fica_indisponivel(): void
    {
        $area = $this->area();
        $this->reservar($area, self::ONTEM, '09:00:00', '10:00:00');

        $dia = $this->dia($this->consultar($area, self::ONTEM, self::ONTEM)->assertStatus(201), self::ONTEM);

        $this->assertSame('unavailable', $dia['status']);
        $this->assertSame('Indisponível', $dia['label']);
        $this->assertSame('past_date', $dia['reason']);
        $this->assertSame('Data passada.', $dia['reason_label']);
        $this->assertFalse($dia['full_day_available']);
        $this->assertSame([], $dia['slots']);
        $this->assertSame([], $dia['free_periods']);
    }

    public function test_hoje_os_blocos_que_ja_comecaram_nao_contam(): void
    {
        $area = $this->area(['closing_time' => '16:00:00']);
        // Reserva da manhã, já passada: não deixa o dia "parcialmente ocupado".
        $this->reservar($area, self::HOJE, '09:00:00', '10:00:00');

        $dia = $this->dia($this->consultar($area, self::HOJE, self::HOJE), self::HOJE);

        // 14h10: o bloco das 14h já começou; sobram 14h30-15h, 15h-15h30 e 15h30-16h.
        $this->assertSame('available', $dia['status']);
        $this->assertSame([['start_time' => '14:30', 'end_time' => '16:00', 'minutes' => 90]], $dia['free_periods']);
        $this->assertSame(['past', 'available', 'available', 'available'], array_slice($this->estados($dia), -4));
        $this->assertFalse($dia['full_day_available']);
    }

    public function test_hoje_depois_do_fechamento_fica_indisponivel(): void
    {
        $dia = $this->dia($this->consultar($this->area(), self::HOJE, self::HOJE), self::HOJE);

        $this->assertSame('unavailable', $dia['status']);
        $this->assertSame('closed_for_today', $dia['reason']);
        $this->assertSame([], $dia['slots']);
        $this->assertFalse($dia['full_day_available']);
    }

    public function test_area_indisponivel_nao_tem_dia_livre(): void
    {
        $area = CommonArea::factory()->unavailable()->create();

        $resposta = $this->consultar($area, self::AMANHA, '2026-11-26')->assertStatus(201);

        $this->assertCount(3, $resposta->json('data.days'));

        foreach ($resposta->json('data.days') as $dia) {
            $this->assertSame('unavailable', $dia['status']);
            $this->assertSame('area_unavailable', $dia['reason']);
            $this->assertFalse($dia['full_day_available']);
        }
    }

    public function test_area_sem_horario_de_funcionamento_fica_indisponivel(): void
    {
        $area = $this->area(['opening_time' => null, 'closing_time' => null]);

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA), self::AMANHA);

        $this->assertSame('unavailable', $dia['status']);
        $this->assertSame('no_opening_hours', $dia['reason']);
    }

    public function test_fechamento_fora_da_grade_encurta_o_ultimo_bloco(): void
    {
        $area = $this->area(['closing_time' => '09:15:00']);

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA), self::AMANHA);

        $this->assertSame(['08:00', '08:30', '09:00'], array_column($dia['slots'], 'start_time'));
        $this->assertSame('09:15', $dia['slots'][2]['end_time']);
        $this->assertSame(75, $dia['free_periods'][0]['minutes']);
    }

    public function test_cada_dia_do_periodo_vem_uma_vez_e_na_ordem(): void
    {
        $area = $this->area();
        $outra = $this->area();
        // Reserva de outra área e reserva no último dia do período.
        $this->reservar($outra, self::AMANHA, null, null);
        $this->reservar($area, '2026-11-30', null, null);

        $resposta = $this->consultar($area, self::ONTEM, '2026-11-30');

        $this->assertSame(
            ['2026-11-22', '2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27', '2026-11-28', '2026-11-29', '2026-11-30'],
            array_column($resposta->json('data.days'), 'date')
        );
        $this->assertSame('available', $this->dia($resposta, self::AMANHA)['status']);
        $this->assertSame('occupied', $this->dia($resposta, '2026-11-30')['status']);
    }

    public function test_a_consulta_nao_identifica_quem_reservou(): void
    {
        $area = $this->area();
        $vizinho = User::factory()->resident()->create(['name' => 'Joana Vizinha', 'email' => 'joana@condovale.test']);
        $reserva = $this->reservar($area, self::AMANHA, '09:00:00', '10:00:00', attributes: ['resident_id' => $vizinho->id]);

        foreach (['availability', 'occupancy'] as $consulta) {
            $resposta = $this->actingAs(User::factory()->resident()->create())
                ->getJson("/api/common-areas/{$area->id}/{$consulta}?start_date=".self::AMANHA.'&end_date='.self::AMANHA)
                ->assertStatus(201);

            $json = $resposta->getContent();
            $this->assertStringNotContainsString('Joana', $json, $consulta);
            $this->assertStringNotContainsString('joana@', $json, $consulta);
            $this->assertStringNotContainsString('resident', $json, $consulta);

            // Fora o id da própria área, nenhum id sai: nem o da reserva.
            $dados = $resposta->json('data');
            unset($dados['common_area']);
            $this->assertStringNotContainsString('"id"', json_encode($dados), $consulta);
            $this->assertNotNull($reserva->id);
        }
    }

    public function test_a_ocupacao_devolve_os_periodos_no_formato_da_api(): void
    {
        $area = $this->area();
        $this->reservar($area, self::AMANHA, '10:00:00', '11:00:00');
        $this->reservar($area, self::AMANHA, '08:00:00', '09:00:00', ReservationStatus::Pending);
        $this->reservar($area, '2026-11-25', null, null);
        $this->reservar($area, self::AMANHA, '11:00:00', '12:00:00', ReservationStatus::Cancelled);

        $this->actingAs(User::factory()->resident()->create())
            ->getJson("/api/common-areas/{$area->id}/occupancy?start_date=".self::AMANHA.'&end_date=2026-11-25')
            ->assertStatus(201)
            ->assertExactJson([
                'status' => true,
                'message' => "Ocupação da área {$area->id} {$area->name} retornada.",
                'data' => [
                    ['date' => self::AMANHA, 'start_time' => '08:00', 'end_time' => '09:00'],
                    ['date' => self::AMANHA, 'start_time' => '10:00', 'end_time' => '11:00'],
                    ['date' => '2026-11-25', 'start_time' => null, 'end_time' => null],
                ],
            ]);
    }

    public function test_o_que_aparece_livre_pode_ser_reservado_e_o_ocupado_nao(): void
    {
        $area = $this->area();
        $morador = User::factory()->resident()->create();
        // Termina às 10h, gravada como o MySQL devolve ("10:00:00").
        $this->reservar($area, self::AMANHA, '08:00:00', '10:00:00');

        $dia = $this->dia($this->consultar($area, self::AMANHA, self::AMANHA), self::AMANHA);
        $livre = $dia['free_periods'][0];

        $this->assertSame(['start_time' => '10:00', 'end_time' => '12:00', 'minutes' => 120], $livre);

        // Encostada na anterior: começar às 10h não é conflito.
        $reserva = CreateReservationAction::execute($morador, $area, [
            'date' => self::AMANHA, 'start_time' => $livre['start_time'], 'end_time' => $livre['end_time'],
        ]);
        $this->assertSame(self::AMANHA, $reserva->date->format('Y-m-d'));

        try {
            CreateReservationAction::execute($morador, $area, ['date' => self::AMANHA, 'start_time' => '09:30', 'end_time' => '10:30']);
            $this->fail('Reservar um bloco ocupado deveria dar conflito.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->getCode());
        }
    }

    public function test_reservar_a_partir_da_abertura_e_aceito(): void
    {
        $area = $this->area();
        // Logado, como na requisição real: o log da reserva registra quem a fez.
        $morador = User::factory()->resident()->create();
        $this->actingAs($morador);

        // Com abertura gravada como "08:00:00", a comparação de texto recusava "08:00".
        $reserva = CreateReservationAction::execute($morador, $area, [
            'date' => self::AMANHA, 'start_time' => '08:00', 'end_time' => '10:00',
        ]);

        $this->assertNotNull($reserva->id);
    }

    public function test_reservar_hoje_um_horario_que_ja_comecou_e_recusado(): void
    {
        $area = $this->area(['closing_time' => '18:00:00']);
        $morador = User::factory()->resident()->create();
        $this->actingAs($morador);

        try {
            CreateReservationAction::execute($morador, $area, ['date' => self::HOJE, 'start_time' => '14:00', 'end_time' => '15:00']);
            $this->fail('Um horário que já começou deveria ser recusado.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame('Não é possível reservar um horário que já passou.', $e->getMessage());
        }

        $reserva = CreateReservationAction::execute($morador, $area, ['date' => self::HOJE, 'start_time' => '14:30', 'end_time' => '15:30']);
        $this->assertNotNull($reserva->id);
    }

    public function test_quem_reserva_e_quem_gerencia_consultam(): void
    {
        $area = $this->area();

        $this->consultar($area, self::AMANHA, self::AMANHA, User::factory()->resident()->create())->assertStatus(201);
        $this->consultar($area, self::AMANHA, self::AMANHA, User::factory()->admin()->create())->assertStatus(201);
    }

    public function test_sindico_e_funcionario_nao_tem_o_caso_de_uso(): void
    {
        $area = $this->area();
        $funcionario = User::factory()->employee()->create();

        $this->consultar($area, self::AMANHA, self::AMANHA, User::factory()->syndic()->create())->assertStatus(403);
        $this->consultar($area, self::AMANHA, self::AMANHA, $funcionario)->assertStatus(403);

        $funcionario->givePermissionTo('view-common-areas');

        $this->consultar($area, self::AMANHA, self::AMANHA, $funcionario)->assertStatus(201);
    }

    public function test_sem_sessao_responde_401(): void
    {
        $area = $this->area();

        $this->getJson("/api/common-areas/{$area->id}/availability?start_date=".self::AMANHA.'&end_date='.self::AMANHA)
            ->assertStatus(401);
    }

    public function test_area_inexistente_responde_404(): void
    {
        $this->actingAs(User::factory()->resident()->create())
            ->getJson('/api/common-areas/999/availability?start_date='.self::AMANHA.'&end_date='.self::AMANHA)
            ->assertStatus(404)
            ->assertJsonPath('error', 'Área comum 999 não encontrada.');
    }

    public function test_o_periodo_e_validado(): void
    {
        $area = $this->area();
        $morador = User::factory()->resident()->create();
        $url = "/api/common-areas/{$area->id}/availability";

        $this->actingAs($morador)->getJson($url)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);

        $this->actingAs($morador)->getJson("{$url}?start_date=2026-11-30&end_date=2026-11-01")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date' => 'A data final deve ser igual ou posterior à inicial.']);

        $this->actingAs($morador)->getJson("{$url}?start_date=2026-11-01&end_date=2027-01-02")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date' => 'Consulte no máximo 62 dias por vez.']);

        // 62 dias, de 1º de novembro a 1º de janeiro, é o teto.
        $this->actingAs($morador)->getJson("{$url}?start_date=2026-11-01&end_date=2027-01-01")
            ->assertStatus(201)
            ->assertJsonCount(62, 'data.days');
    }
}
