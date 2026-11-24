<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Log;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * RF03 — UC16 (aprovar ou rejeitar), UC17 (minhas reservas) e UC19
 * (gerenciar reservas): as rotas que a tela de reservas já chamava.
 */
class ReservationManagementTest extends TestCase
{
    use RefreshDatabase;

    private const AMANHA = '2026-11-25';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-24 09:00:00'));
    }

    private function area(array $attributes = []): CommonArea
    {
        return CommonArea::factory()->create([
            'name' => 'Salão de festas',
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'max_reservation_minutes' => 240,
            'requires_approval' => true,
            ...$attributes,
        ]);
    }

    private function morador(string $nome = 'Ana Moradora'): User
    {
        $unidade = Unit::factory()->create(['code' => 'A-'.fake()->unique()->numberBetween(100, 999)]);

        return User::factory()->resident()->create(['name' => $nome, 'unit_id' => $unidade->id]);
    }

    /** Gravada como o MySQL devolve os horários ("HH:MM:SS"). */
    private function reserva(User $morador, CommonArea $area, array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'common_area_id' => $area->id,
            'resident_id' => $morador->id,
            'date' => self::AMANHA,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => ReservationStatus::Pending,
            ...$attributes,
        ]);
    }

    // --- UC17 — minhas reservas ---------------------------------------------

    public function test_o_morador_ve_so_as_proprias_reservas(): void
    {
        $area = $this->area();
        $ana = $this->morador();
        $outro = $this->morador('Bruno Vizinho');

        $minha = $this->reserva($ana, $area);
        $diaria = $this->reserva($ana, $area, ['date' => '2026-11-28', 'start_time' => null, 'end_time' => null, 'status' => ReservationStatus::Approved]);
        $this->reserva($outro, $area, ['start_time' => '14:00:00', 'end_time' => '16:00:00']);

        $resposta = $this->actingAs($ana)->getJson('/api/reservations/me')
            ->assertStatus(201)
            ->assertJsonPath('total', 2);

        // Mais recente primeiro.
        $this->assertSame([$diaria->id, $minha->id], array_column($resposta->json('data'), 'id'));

        // O filtro com o id do vizinho não abre as reservas dele: soma-se ao recorte da sessão.
        $this->actingAs($ana)->getJson('/api/reservations/me?filters=resident_id:=:'.$outro->id)
            ->assertStatus(201)
            ->assertJsonPath('total', 0);

        $linha = $resposta->json('data.1');
        $this->assertSame('10:00', $linha['start_time']);
        $this->assertSame('12:00', $linha['end_time']);
        $this->assertSame('Pendente', $linha['status_label']);
        $this->assertFalse($linha['full_day']);
        $this->assertSame(['id' => $area->id, 'name' => 'Salão de festas'], $linha['common_area']);
        $this->assertArrayNotHasKey('resident', $linha);

        $this->assertTrue($resposta->json('data.0.full_day'));
        $this->assertNull($resposta->json('data.0.start_time'));
    }

    public function test_reserva_que_termina_a_meia_noite_aparece_como_24h(): void
    {
        $ana = $this->morador();
        $this->reserva($ana, $this->area(['closing_time' => '24:00:00']), ['start_time' => '22:00:00', 'end_time' => '24:00:00']);

        $this->actingAs($ana)->getJson('/api/reservations/me')
            ->assertStatus(201)
            ->assertJsonPath('data.0.end_time', '24:00');
    }

    public function test_quem_nao_reserva_nao_tem_minhas_reservas(): void
    {
        foreach (['employee', 'syndic', 'admin'] as $perfil) {
            $this->actingAs(User::factory()->{$perfil}()->create())->getJson('/api/reservations/me')->assertStatus(403);
        }
    }

    // --- UC19 — gerenciar reservas ------------------------------------------

    public function test_a_administracao_ve_todas_com_quem_reservou(): void
    {
        $area = $this->area();
        $ana = $this->morador();
        $bruno = $this->morador('Bruno Vizinho');
        $this->reserva($ana, $area);
        $this->reserva($bruno, $area, ['date' => '2026-11-26', 'status' => ReservationStatus::Approved]);

        $resposta = $this->actingAs(User::factory()->admin()->create())->getJson('/api/reservations')
            ->assertStatus(201)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.resident.name', 'Bruno Vizinho')
            ->assertJsonPath('data.0.resident.unit.code', $bruno->unit->code)
            ->assertJsonPath('data.1.resident.id', $ana->id);

        // Do morador saem nome e unidade; e-mail não.
        $this->assertSame(['id', 'name', 'unit_id', 'unit'], array_keys($resposta->json('data.0.resident')));

        $this->getJson('/api/reservations?filters=status:=:pending')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.resident.id', $ana->id);
    }

    public function test_relations_da_query_string_nao_abre_o_cadastro_de_ninguem(): void
    {
        $ana = $this->morador();
        $this->reserva($ana, $this->area());

        $resposta = $this->actingAs($ana)->getJson('/api/reservations/me?relations=resident')->assertStatus(201);

        $this->assertArrayNotHasKey('resident', $resposta->json('data.0'));
        $this->assertStringNotContainsString($ana->email, $resposta->getContent());
    }

    public function test_so_a_administracao_lista_todas(): void
    {
        foreach (['resident', 'employee', 'syndic'] as $perfil) {
            $this->actingAs(User::factory()->{$perfil}()->create())->getJson('/api/reservations')->assertStatus(403);
        }

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/reservations')->assertStatus(401);
    }

    // --- Detalhe -------------------------------------------------------------

    public function test_detalhe_para_o_dono_e_para_a_administracao(): void
    {
        $ana = $this->morador();
        $reserva = $this->reserva($ana, $this->area());

        $this->actingAs($ana)->getJson("/api/reservations/{$reserva->id}")
            ->assertStatus(201)
            ->assertJsonPath('message', "Reserva {$reserva->id} encontrada.")
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonMissingPath('data.resident');

        $this->actingAs(User::factory()->admin()->create())->getJson("/api/reservations/{$reserva->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.resident.name', 'Ana Moradora');
    }

    public function test_reserva_de_outra_pessoa_responde_como_inexistente(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area());
        $outro = $this->morador('Bruno Vizinho');

        $this->actingAs($outro)->getJson("/api/reservations/{$reserva->id}")
            ->assertStatus(404)
            ->assertJsonPath('error', "Reserva {$reserva->id} não encontrada.");

        $this->actingAs($outro)->getJson('/api/reservations/999999')->assertStatus(404);
    }

    // --- UC16 — aprovar ou reprovar -----------------------------------------

    public function test_a_administracao_aprova_um_pedido(): void
    {
        $area = $this->area();
        $reserva = $this->reserva($this->morador(), $area);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patchJson("/api/reservations/{$reserva->id}/status", ['status' => 'approved'])
            ->assertStatus(201)
            ->assertJsonPath('message', "Reserva {$reserva->id} da área {$area->id} Salão de festas para 25/11/2026, das 10:00 às 12:00, aprovada.")
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.status_label', 'Aprovada')
            ->assertJsonPath('data.rejection_reason', null);

        $log = Log::where('action', AuditAction::ReservationApproved->value)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($reserva->id, $log->subject_id);
    }

    public function test_a_administracao_reprova_com_motivo(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area());

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/reservations/{$reserva->id}/status", ['status' => 'rejected', 'rejection_reason' => '  Área em manutenção.  '])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Área em manutenção.');

        $this->assertSame(1, Log::where('action', AuditAction::ReservationRejected->value)->count());
    }

    public function test_o_motivo_da_reprovacao_e_opcional(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area());

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/reservations/{$reserva->id}/status", ['status' => 'rejected', 'rejection_reason' => ''])
            ->assertStatus(201)
            ->assertJsonPath('data.rejection_reason', null);
    }

    public function test_so_quem_aprova_decide(): void
    {
        $ana = $this->morador();
        $reserva = $this->reserva($ana, $this->area());

        foreach ([$ana, User::factory()->employee()->create(), User::factory()->syndic()->create()] as $quem) {
            $this->actingAs($quem)->patchJson("/api/reservations/{$reserva->id}/status", ['status' => 'approved'])->assertStatus(403);
        }

        $this->assertSame(ReservationStatus::Pending, $reserva->refresh()->status);
    }

    public function test_so_pedido_pendente_e_analisado(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area(), ['status' => ReservationStatus::Cancelled]);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/reservations/{$reserva->id}/status", ['status' => 'approved'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Somente solicitações pendentes podem ser analisadas.');
    }

    public function test_aprovar_respeita_conflito_e_regras_da_area(): void
    {
        $area = $this->area();
        $this->reserva($this->morador(), $area, ['status' => ReservationStatus::Approved]);
        $sobreposta = $this->reserva($this->morador('Bruno Vizinho'), $area, ['start_time' => '11:00:00', 'end_time' => '13:00:00']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patchJson("/api/reservations/{$sobreposta->id}/status", ['status' => 'approved'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe uma reserva para esta área, data e horário.');

        $fechada = $this->reserva($this->morador('Carla'), $this->area(['name' => 'Quadra']));
        $fechada->commonArea->update(['status' => 'unavailable']);

        $this->actingAs($admin)->patchJson("/api/reservations/{$fechada->id}/status", ['status' => 'approved'])
            ->assertStatus(422)
            ->assertJsonPath('errors.common_area_id.0', 'Esta solicitação não pode mais ser aprovada: esta área comum não está disponível para reserva. Reprove-a informando o motivo.');
    }

    public function test_status_fora_da_analise_e_recusado(): void
    {
        $reserva = $this->reserva($this->morador(), $this->area());

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/reservations/{$reserva->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status' => 'A análise permite apenas aprovar ou reprovar a solicitação.']);

        $this->patchJson('/api/reservations/999999/status', ['status' => 'approved'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'Reserva 999999 não encontrada.');
    }
}
