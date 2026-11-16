<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
use App\Enums\UnitStatus;
use App\Models\CommonArea;
use App\Models\Log;
use App\Models\Notice;
use App\Models\Occurrence;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O painel inicial: cada seção aparece para quem tem o caso de uso, e não
 * aparece — nem vazia — para quem não tem.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** As chaves que todo painel tem, além das seções. */
    private const BASE = ['cards', 'generated_at', 'user'];

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda-feira, 16 de novembro de 2026, no meio do mês.
        $this->travelTo('2026-11-16 10:00:00');
    }

    private function painel(User $user)
    {
        return $this->actingAs($user)->getJson('/api/dashboard')->assertStatus(201);
    }

    private function secoes(User $user): array
    {
        $chaves = array_keys($this->painel($user)->json('data'));
        sort($chaves);

        return $chaves;
    }

    private function esperado(string ...$secoes): array
    {
        $chaves = [...self::BASE, ...$secoes];
        sort($chaves);

        return $chaves;
    }

    private function moradorComUnidade(array $attributes = []): User
    {
        $morador = User::factory()->resident()->create($attributes);
        UnitOccupancy::factory()->create(['user_id' => $morador->id]);

        return $morador->fresh();
    }

    public function test_cada_perfil_recebe_as_secoes_dos_seus_casos_de_uso(): void
    {
        $this->assertSame(
            $this->esperado('my_occurrences', 'my_reservations', 'notices'),
            $this->secoes(User::factory()->resident()->create())
        );

        $this->assertSame(
            $this->esperado('my_assignments'),
            $this->secoes(User::factory()->employee()->create())
        );

        $this->assertSame(
            $this->esperado('occurrence_queue', 'units', 'month', 'notices'),
            $this->secoes(User::factory()->syndic()->create())
        );

        $this->assertSame(
            $this->esperado('occurrence_queue', 'reservations', 'units', 'people', 'month', 'notices', 'recent_activity'),
            $this->secoes(User::factory()->admin()->create())
        );
    }

    public function test_concessao_individual_traz_a_secao_junto(): void
    {
        $funcionario = User::factory()->employee()->create();
        $funcionario->givePermissionTo('view-notices');

        $sindico = User::factory()->syndic()->create();
        $sindico->givePermissionTo('view-audit-reports');

        $this->assertSame($this->esperado('my_assignments', 'notices'), $this->secoes($funcionario));
        $this->assertContains('recent_activity', $this->secoes($sindico));
    }

    public function test_o_perfil_de_quem_esta_logado(): void
    {
        $morador = $this->moradorComUnidade(['name' => 'Ana Silva']);

        $this->painel($morador)
            ->assertJsonPath('message', 'Painel de Ana Silva.')
            ->assertJsonPath('data.user.name', 'Ana Silva')
            ->assertJsonPath('data.user.role', 'resident')
            ->assertJsonPath('data.user.role_label', 'Morador')
            ->assertJsonPath('data.user.unit.code', $morador->unit->code)
            ->assertJsonPath('data.generated_at', '2026-11-16T10:00:00-03:00');

        $this->painel(User::factory()->syndic()->create())->assertJsonPath('data.user.unit', null);
    }

    public function test_o_morador_ve_os_proprios_chamados(): void
    {
        $morador = User::factory()->resident()->create();
        Occurrence::factory()->create(['resident_id' => $morador->id, 'status' => OccurrenceStatus::Open, 'updated_at' => '2026-11-10']);
        $recente = Occurrence::factory()->create(['resident_id' => $morador->id, 'status' => OccurrenceStatus::InProgress, 'updated_at' => '2026-11-15']);
        Occurrence::factory()->create(['resident_id' => $morador->id, 'status' => OccurrenceStatus::Completed, 'updated_at' => '2026-11-01']);
        // De outro morador: não conta.
        Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        $resposta = $this->painel($morador)
            ->assertJsonPath('data.my_occurrences.open', 2)
            ->assertJsonPath('data.my_occurrences.latest.0.id', $recente->id)
            ->assertJsonPath('data.my_occurrences.latest.0.status_label', 'Em andamento')
            ->assertJsonCount(3, 'data.my_occurrences.latest');

        $this->assertSame(1, collect($resposta->json('data.my_occurrences.by_status'))->firstWhere('status', 'completed')['total']);
    }

    public function test_o_morador_ve_as_proprias_proximas_reservas(): void
    {
        $morador = User::factory()->resident()->create();
        $area = CommonArea::factory()->create(['name' => 'Churrasqueira']);

        $amanha = Reservation::factory()->create(['resident_id' => $morador->id, 'common_area_id' => $area->id, 'date' => '2026-11-17', 'status' => ReservationStatus::Pending]);
        Reservation::factory()->create(['resident_id' => $morador->id, 'common_area_id' => $area->id, 'date' => '2026-11-20', 'status' => ReservationStatus::Approved]);
        // Passada, cancelada e de outro morador ficam de fora.
        Reservation::factory()->create(['resident_id' => $morador->id, 'common_area_id' => $area->id, 'date' => '2026-11-10']);
        Reservation::factory()->create(['resident_id' => $morador->id, 'common_area_id' => $area->id, 'date' => '2026-11-25', 'status' => ReservationStatus::Cancelled]);
        Reservation::factory()->create(['common_area_id' => $area->id, 'date' => '2026-11-18']);

        $this->painel($morador)
            ->assertJsonPath('data.my_reservations.upcoming_total', 2)
            ->assertJsonPath('data.my_reservations.pending', 1)
            ->assertJsonPath('data.my_reservations.upcoming.0.id', $amanha->id)
            ->assertJsonPath('data.my_reservations.upcoming.0.common_area.name', 'Churrasqueira')
            ->assertJsonPath('data.my_reservations.upcoming.0.status_label', 'Pendente');
    }

    public function test_o_funcionario_ve_a_propria_fila_do_mais_antigo_para_o_mais_novo(): void
    {
        $funcionario = User::factory()->employee()->create();
        $antigo = Occurrence::factory()->assignedTo($funcionario)->create(['created_at' => '2026-11-06 10:00:00']);
        Occurrence::factory()->assignedTo($funcionario)->create(['created_at' => '2026-11-12 10:00:00']);
        Occurrence::factory()->inProgressWith($funcionario)->create(['created_at' => '2026-11-14 10:00:00']);
        Occurrence::factory()->create(['assigned_employee_id' => $funcionario->id, 'status' => OccurrenceStatus::Completed, 'completed_at' => '2026-11-03 10:00:00']);
        // Concluído no mês passado e chamado de colega não contam.
        Occurrence::factory()->create(['assigned_employee_id' => $funcionario->id, 'status' => OccurrenceStatus::Completed, 'completed_at' => '2026-10-28 10:00:00']);
        Occurrence::factory()->assignedTo(User::factory()->employee()->create())->create();

        $this->painel($funcionario)
            ->assertJsonPath('data.my_assignments.to_start', 2)
            ->assertJsonPath('data.my_assignments.in_progress', 1)
            ->assertJsonPath('data.my_assignments.completed_this_month', 1)
            ->assertJsonCount(3, 'data.my_assignments.queue')
            ->assertJsonPath('data.my_assignments.queue.0.id', $antigo->id)
            ->assertJsonPath('data.my_assignments.queue.0.waiting_days', 10);
    }

    public function test_a_fila_do_condominio_por_etapa(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $maisAntigo = Occurrence::factory()->create(['resident_id' => $morador->id, 'status' => OccurrenceStatus::Open, 'created_at' => '2026-11-01 10:00:00']);
        Occurrence::factory()->create(['status' => OccurrenceStatus::Open, 'created_at' => '2026-11-10 10:00:00']);
        Occurrence::factory()->create(['status' => OccurrenceStatus::Analysis]);
        Occurrence::factory()->assignedTo(User::factory()->employee()->create())->create();
        Occurrence::factory()->create(['status' => OccurrenceStatus::Completed, 'created_at' => '2026-10-01 10:00:00']);
        Occurrence::factory()->create(['status' => OccurrenceStatus::Cancelled]);

        $resposta = $this->painel(User::factory()->syndic()->create())
            ->assertJsonPath('data.occurrence_queue.open_total', 4)
            ->assertJsonPath('data.occurrence_queue.awaiting_analysis', 2)
            ->assertJsonPath('data.occurrence_queue.awaiting_assignment', 1)
            ->assertJsonPath('data.occurrence_queue.assigned', 1)
            ->assertJsonPath('data.occurrence_queue.in_progress', 0)
            // O concluído de outubro é mais antigo, mas não está na fila.
            ->assertJsonPath('data.occurrence_queue.oldest_waiting.0.id', $maisAntigo->id)
            ->assertJsonPath('data.occurrence_queue.oldest_waiting.0.resident', ['id' => $morador->id, 'name' => 'Ana Silva'])
            ->assertJsonPath('data.occurrence_queue.oldest_waiting.0.waiting_days', 15);

        $this->assertStringNotContainsString('@', json_encode($resposta->json('data.occurrence_queue')));
    }

    public function test_a_agenda_para_quem_aprova_reservas(): void
    {
        $area = CommonArea::factory()->create(['name' => 'Salão de festas']);
        $morador = $this->moradorComUnidade(['name' => 'Bruno Costa']);

        Reservation::factory()->create(['common_area_id' => $area->id, 'resident_id' => $morador->id, 'date' => '2026-11-16', 'status' => ReservationStatus::Approved]);
        Reservation::factory()->create(['common_area_id' => $area->id, 'date' => '2026-11-19', 'status' => ReservationStatus::Pending]);
        Reservation::factory()->create(['common_area_id' => $area->id, 'date' => '2026-11-30', 'status' => ReservationStatus::Pending]);
        Reservation::factory()->create(['common_area_id' => $area->id, 'date' => '2026-11-21', 'status' => ReservationStatus::Approved]);
        // Passada e recusada não entram.
        Reservation::factory()->create(['common_area_id' => $area->id, 'date' => '2026-11-12', 'status' => ReservationStatus::Pending]);
        Reservation::factory()->create(['common_area_id' => $area->id, 'date' => '2026-11-18', 'status' => ReservationStatus::Rejected]);

        $this->painel(User::factory()->admin()->create())
            ->assertJsonPath('data.reservations.awaiting_approval', 2)
            ->assertJsonPath('data.reservations.today', 1)
            ->assertJsonPath('data.reservations.next_7_days', 3)
            ->assertJsonCount(4, 'data.reservations.upcoming')
            ->assertJsonPath('data.reservations.upcoming.0.resident.name', 'Bruno Costa')
            ->assertJsonPath('data.reservations.upcoming.0.resident.unit.code', $morador->unit->code);
    }

    public function test_ocupacao_das_unidades(): void
    {
        $ocupada = Unit::factory()->create();
        UnitOccupancy::factory()->create(['unit_id' => $ocupada->id]);
        Unit::factory()->create();
        Unit::factory()->create(['status' => UnitStatus::Inactive]);
        // Vínculo encerrado não ocupa.
        $desocupada = Unit::factory()->create();
        UnitOccupancy::factory()->ended()->create(['unit_id' => $desocupada->id]);

        $this->painel(User::factory()->syndic()->create())
            ->assertJsonPath('data.units', ['total' => 4, 'active' => 3, 'inactive' => 1, 'occupied' => 1, 'vacant' => 2]);
    }

    public function test_pessoas_para_quem_cadastra(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->resident()->count(2)->create();
        User::factory()->resident()->mustChangePassword()->create();
        User::factory()->resident()->inactive()->create();
        User::factory()->employee()->create();

        $this->painel($admin)->assertJsonPath('data.people', [
            'residents_active' => 3,
            'employees_active' => 1,
            'syndics_active' => 0,
            'inactive_accounts' => 1,
            'first_access_pending' => 1,
        ]);
    }

    public function test_o_mes_corrente_com_a_conta_dos_relatorios(): void
    {
        Occurrence::factory()->create(['status' => OccurrenceStatus::Completed, 'created_at' => '2026-11-02 08:00:00', 'completed_at' => '2026-11-02 18:00:00']);
        Occurrence::factory()->create(['status' => OccurrenceStatus::Open, 'created_at' => '2026-11-10 08:00:00']);
        // Do mês passado: fora.
        Occurrence::factory()->create(['status' => OccurrenceStatus::Open, 'created_at' => '2026-10-30 08:00:00']);
        Reservation::factory()->create(['date' => '2026-11-28', 'status' => ReservationStatus::Approved]);
        Reservation::factory()->create(['date' => '2026-12-01', 'status' => ReservationStatus::Approved]);

        $this->painel(User::factory()->syndic()->create())
            ->assertJsonPath('data.month.from', '2026-11-01')
            ->assertJsonPath('data.month.to', '2026-11-30')
            ->assertJsonPath('data.month.occurrences.total', 2)
            ->assertJsonPath('data.month.occurrences.completed', 1)
            ->assertJsonPath('data.month.occurrences.average_resolution_hours', 10)
            ->assertJsonPath('data.month.reservations.total', 1)
            ->assertJsonPath('data.month.reservations.approved', 1);
    }

    public function test_o_mural_e_os_rascunhos_so_para_quem_publica(): void
    {
        Notice::factory()->create(['title' => 'Antigo', 'published_at' => now()->subDays(9)]);
        Notice::factory()->create(['title' => 'Recente', 'published_at' => now()->subDay()]);
        Notice::factory()->draft()->create();

        $this->painel(User::factory()->resident()->create())
            ->assertJsonPath('data.notices.published', 2)
            ->assertJsonPath('data.notices.latest.0.title', 'Recente')
            ->assertJsonMissingPath('data.notices.drafts');

        $this->painel(User::factory()->syndic()->create())
            ->assertJsonPath('data.notices.drafts', 1);
    }

    public function test_atividade_recente_sem_os_dados_gravados(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana Administração']);

        foreach (range(1, 6) as $i) {
            Log::create(['type_log_id' => TypeLogEnum::NOTICE->value, 'user_id' => $admin->id, 'description' => "Ação {$i}", 'ip' => '10.0.0.1', 'data_log' => ['cpf' => '123']]);
        }

        $atividade = $this->painel($admin)
            ->assertJsonCount(5, 'data.recent_activity')
            ->assertJsonPath('data.recent_activity.0.description', 'Ação 6')
            ->assertJsonPath('data.recent_activity.0.user', ['id' => $admin->id, 'name' => 'Ana Administração'])
            ->json('data.recent_activity');

        $this->assertStringNotContainsString('cpf', json_encode($atividade));
        $this->assertStringNotContainsString('10.0.0.1', json_encode($atividade));
    }

    public function test_os_cards_seguem_as_secoes_de_cada_perfil(): void
    {
        $cards = fn (User $user) => array_column($this->painel($user)->json('data.cards'), 'key');

        $this->assertSame(['my_open_occurrences', 'my_upcoming_reservations', 'published_notices'], $cards(User::factory()->resident()->create()));
        $this->assertSame(['assignments_to_start', 'assignments_in_progress', 'assignments_completed_month'], $cards(User::factory()->employee()->create()));
        $this->assertSame(
            ['open_occurrences', 'reservations_awaiting_approval', 'occupied_units', 'active_residents', 'completed_this_month', 'published_notices'],
            $cards(User::factory()->admin()->create())
        );
    }

    public function test_cada_card_traz_valor_e_explicacao(): void
    {
        Occurrence::factory()->count(2)->create(['status' => OccurrenceStatus::Open]);
        Occurrence::factory()->create(['status' => OccurrenceStatus::Analysis]);
        Notice::factory()->draft()->create();

        $cards = collect($this->painel(User::factory()->syndic()->create())->json('data.cards'))->keyBy('key');

        $this->assertSame(['key' => 'open_occurrences', 'label' => 'Ocorrências abertas', 'value' => 3, 'note' => '2 aguardando análise'], $cards['open_occurrences']);
        $this->assertSame('publicados · 1 em rascunho', $cards['published_notices']['note']);
        $this->assertSame('nenhuma conclusão no mês', $cards['completed_this_month']['note']);
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
    }
}
