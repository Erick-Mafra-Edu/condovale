<?php

namespace Tests\Feature;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AreaComum.horarioInicio / horarioFim — a configuração dos horários e a
 * disponibilidade que sai deles.
 *
 * O funcionamento é no mesmo dia; a meia-noite que encerra o dia se escreve
 * "24:00" (e "00:00" no fechamento quer dizer o mesmo). A área fica aberta
 * pelo menos um bloco de 30 minutos.
 */
class CommonAreaHoursTest extends TestCase
{
    use RefreshDatabase;

    private const AMANHA = '2026-11-25';

    private const MEIA_NOITE = 'O horário de fechamento deve ser posterior ao de abertura, no mesmo dia. Para fechar à meia-noite, use 24:00.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-24 09:00:00'));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function morador(): User
    {
        $morador = User::factory()->resident()->create();
        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);

        return $morador;
    }

    private function cadastrar(array $horarios)
    {
        return $this->actingAs($this->admin())->postJson('/api/common-areas', [
            'name' => 'Salão de festas',
            'max_reservation_minutes' => 240,
            ...$horarios,
        ]);
    }

    private function dia(CommonArea $area, User $quem): array
    {
        return $this->actingAs($quem)
            ->getJson("/api/common-areas/{$area->id}/availability?start_date=".self::AMANHA.'&end_date='.self::AMANHA)
            ->assertStatus(201)
            ->json('data.days.0');
    }

    public function test_a_area_que_fecha_a_meia_noite_reserva_ate_24h(): void
    {
        $id = $this->cadastrar(['opening_time' => '18:00', 'closing_time' => '24:00'])
            ->assertStatus(201)
            ->assertJsonPath('data.closing_time', '24:00')
            ->json('data.id');
        $area = CommonArea::find($id);
        $morador = $this->morador();

        $dia = $this->dia($area, $morador);
        $this->assertSame(['start_time' => '23:30', 'end_time' => '24:00', 'status' => 'available'], end($dia['slots']));
        $this->assertSame([['start_time' => '18:00', 'end_time' => '24:00', 'minutes' => 360]], $dia['free_periods']);

        // O último bloco, como a tela de reservas o envia.
        $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $id, 'date' => self::AMANHA, 'start_time' => '22:00', 'end_time' => '24:00',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.end_time', '24:00');

        // "00:00" no fim também é meia-noite: cruza com a de cima.
        $this->actingAs($this->morador())->postJson('/api/reservations', [
            'common_area_id' => $id, 'date' => self::AMANHA, 'start_time' => '23:30', 'end_time' => '00:00',
        ])->assertStatus(409);

        $this->actingAs($morador)
            ->getJson("/api/common-areas/{$id}/occupancy?start_date=".self::AMANHA.'&end_date='.self::AMANHA)
            ->assertJsonPath('data.0', ['date' => self::AMANHA, 'start_time' => '22:00', 'end_time' => '24:00']);
    }

    public function test_00_00_no_fechamento_e_gravado_como_24_00(): void
    {
        $id = $this->cadastrar(['opening_time' => '10:00', 'closing_time' => '00:00'])
            ->assertStatus(201)
            ->assertJsonPath('data.closing_time', '24:00')
            ->json('data.id');

        $this->assertSame('24:00', CommonArea::find($id)->closing_time);
    }

    public function test_area_aberta_o_dia_inteiro(): void
    {
        $id = $this->cadastrar(['opening_time' => '00:00', 'closing_time' => '24:00'])->assertStatus(201)->json('data.id');

        $dia = $this->dia(CommonArea::find($id), $this->morador());

        $this->assertCount(48, $dia['slots']);
        $this->assertSame('00:00', $dia['slots'][0]['start_time']);
        $this->assertSame('24:00', $dia['slots'][47]['end_time']);
    }

    public function test_formatos_de_horario_recusados(): void
    {
        foreach (['24:30', '25:00', '7:00', '08:00:00', 'meia-noite'] as $fechamento) {
            $this->cadastrar(['opening_time' => '08:00', 'closing_time' => $fechamento])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['closing_time' => 'Informe o horário de fechamento no formato HH:MM, ou 24:00 para meia-noite.']);
        }

        // A abertura não é meia-noite do fim do dia.
        $this->cadastrar(['opening_time' => '24:00', 'closing_time' => '24:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time' => 'Informe o horário de abertura no formato HH:MM.']);

        $this->assertDatabaseCount('common_areas', 0);
    }

    public function test_o_funcionamento_nao_vira_a_meia_noite(): void
    {
        $this->cadastrar(['opening_time' => '18:00', 'closing_time' => '02:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closing_time' => self::MEIA_NOITE]);
    }

    public function test_a_area_fica_aberta_pelo_menos_um_bloco(): void
    {
        $this->cadastrar(['opening_time' => '08:00', 'closing_time' => '08:20'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closing_time' => 'A área precisa ficar aberta por pelo menos 30 minutos, o tamanho de um bloco de reserva.']);

        $id = $this->cadastrar(['opening_time' => '08:00', 'closing_time' => '08:30'])->assertStatus(201)->json('data.id');

        $this->assertCount(1, $this->dia(CommonArea::find($id), $this->morador())['slots']);
    }

    public function test_editar_um_horario_so_confere_com_o_outro_gravado(): void
    {
        $admin = $this->admin();
        $area = CommonArea::factory()->create(['opening_time' => '08:00:00', 'closing_time' => '22:00:00']);
        $url = "/api/common-areas/{$area->id}";

        $this->actingAs($admin)->patchJson($url, ['closing_time' => '00:00'])
            ->assertStatus(201)
            ->assertJsonPath('data.closing_time', '24:00');

        // Só a abertura veio: a recusa aponta para ela.
        $this->actingAs($admin)->patchJson($url, ['opening_time' => '23:45'])
            ->assertStatus(422)
            ->assertJsonPath('errors.opening_time', ['A área precisa ficar aberta por pelo menos 30 minutos, o tamanho de um bloco de reserva.']);

        $this->actingAs($admin)->patchJson($url, ['closing_time' => '07:00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.closing_time', [self::MEIA_NOITE]);

        $this->assertSame('08:00', $area->refresh()->opening_time);
        $this->assertSame('24:00', $area->closing_time);
    }

    public function test_a_disponibilidade_e_a_reserva_seguem_o_horario_configurado(): void
    {
        $admin = $this->admin();
        $morador = $this->morador();
        $area = CommonArea::factory()->create(['opening_time' => '08:00:00', 'closing_time' => '22:00:00', 'max_reservation_minutes' => 240]);

        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['opening_time' => '10:00', 'closing_time' => '14:00'])
            ->assertStatus(201);

        $dia = $this->dia($area, $morador);
        $this->assertSame(['10:00', '14:00'], [$dia['slots'][0]['start_time'], end($dia['slots'])['end_time']]);
        $this->assertCount(8, $dia['slots']);

        $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => '09:00', 'end_time' => '11:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.start_time', ['A área abre às 10:00.']);

        $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => '12:00', 'end_time' => '24:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.end_time', ['A área fecha às 14:00.']);
    }

    public function test_o_fim_da_reserva_aceita_so_horario_valido(): void
    {
        $area = CommonArea::factory()->create(['opening_time' => '08:00:00', 'closing_time' => '24:00:00']);

        $this->actingAs($this->morador())->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => self::AMANHA, 'start_time' => '23:00', 'end_time' => '24:30',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_time' => 'Informe o horário final no formato HH:MM, ou 24:00 para meia-noite.']);
    }

    public function test_reserva_que_termina_a_meia_noite_ainda_se_cancela_a_noite(): void
    {
        $this->travelTo(Carbon::parse(self::AMANHA.' 23:10:00'));
        $morador = $this->morador();
        $area = CommonArea::factory()->create(['opening_time' => '18:00:00', 'closing_time' => '24:00:00']);
        $reserva = Reservation::factory()->create([
            'common_area_id' => $area->id, 'resident_id' => $morador->id, 'date' => self::AMANHA,
            'start_time' => '22:00:00', 'end_time' => '24:00:00', 'status' => 'approved',
        ]);

        // Às 23h10 ela está em andamento, não terminada.
        $this->actingAs($morador)->postJson("/api/reservations/{$reserva->id}/cancel")->assertStatus(201);
    }
}
