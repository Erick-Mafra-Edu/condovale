<?php

namespace App\Http\Actions;

use App\Enums\NoticeStatus;
use App\Enums\OccurrenceStatus;
use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Enums\UseCase;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Log;
use App\Models\Notice;
use App\Models\Occurrence;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * O painel inicial, montado pelo que a pessoa pode fazer.
 *
 * Cada seção depende de um caso de uso, e não do perfil: quem recebeu uma
 * concessão individual ganha a seção junto com a permissão, e quem perdeu
 * deixa de vê-la. Uma seção que a pessoa não pode ver simplesmente não vem na
 * resposta — não vem vazia nem zerada.
 *
 * `cards` são os indicadores já prontos para a fileira do topo, na ordem de
 * importância para quem está olhando; as seções trazem os números e as listas
 * que os explicam. Tudo sai de contagens agrupadas e listas curtas, em número
 * de consultas que não cresce com o volume de dados (RNF01).
 */
class BuildDashboardAction
{
    /** Quantos itens cada lista curta do painel mostra. */
    private const LISTA = 5;

    /**
     * @return array<string, mixed>
     */
    public static function execute(User $user): array
    {
        $agora = CarbonImmutable::now();

        $secoes = array_filter([
            'my_occurrences' => self::pode($user, UseCase::CreateOccurrence, UseCase::TrackOwnOccurrences)
                ? self::myOccurrences($user) : null,
            'my_reservations' => self::pode($user, UseCase::ViewOwnReservations)
                ? self::myReservations($user, $agora) : null,
            'my_assignments' => self::pode($user, UseCase::ViewAssignedOccurrences)
                ? self::myAssignments($user, $agora) : null,
            'occurrence_queue' => self::pode($user, UseCase::AnalyzeOccurrences, UseCase::AssignOccurrence, UseCase::CloseAnyOccurrence)
                ? self::occurrenceQueue($agora) : null,
            'reservations' => self::pode($user, UseCase::ManageReservations, UseCase::ApproveOrRejectReservation)
                ? self::reservations($agora) : null,
            'units' => self::pode($user, UseCase::ManageUnits)
                ? self::units() : null,
            'people' => self::pode($user, UseCase::ManageResidents)
                ? self::people() : null,
            'month' => self::pode($user, UseCase::GenerateReports)
                ? self::month($agora) : null,
            'notices' => self::pode($user, UseCase::ViewNotices, UseCase::PublishNotices)
                ? self::notices($user) : null,
            'recent_activity' => self::pode($user, UseCase::ViewAuditReports)
                ? self::recentActivity() : null,
        ], fn ($secao) => $secao !== null);

        return [
            'generated_at' => $agora->toIso8601String(),
            'user' => self::profile($user),
            'cards' => self::cards($secoes),
            ...$secoes,
        ];
    }

    private static function pode(User $user, UseCase ...$casos): bool
    {
        foreach ($casos as $caso) {
            if ($user->hasUseCase($caso)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{id: int, name: string, role: string, role_label: string, unit: array{id: int, code: string}|null}
     */
    private static function profile(User $user): array
    {
        $unidade = $user->unit_id ? Unit::query()->select(['id', 'code'])->find($user->unit_id) : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'unit' => $unidade ? ['id' => $unidade->id, 'code' => $unidade->code] : null,
        ];
    }

    /**
     * Os indicadores da fileira do topo, montados só a partir das seções que
     * a pessoa pode ver. A ordem é a de importância: primeiro o que é dela,
     * depois o que ela precisa resolver, por último o panorama.
     *
     * @param  array<string, array<string, mixed>>  $secoes
     * @return list<array{key: string, label: string, value: int, note: string}>
     */
    private static function cards(array $secoes): array
    {
        $cards = [];
        $card = function (string $key, string $label, int $value, string $note) use (&$cards) {
            $cards[] = ['key' => $key, 'label' => $label, 'value' => $value, 'note' => $note];
        };

        if (isset($secoes['my_occurrences'])) {
            $card('my_open_occurrences', 'Meus chamados abertos', $secoes['my_occurrences']['open'], 'em acompanhamento');
        }

        if (isset($secoes['my_reservations'])) {
            $card('my_upcoming_reservations', 'Minhas próximas reservas', $secoes['my_reservations']['upcoming_total'], 'pendentes ou aprovadas');
        }

        if (isset($secoes['my_assignments'])) {
            $card('assignments_to_start', 'Para iniciar', $secoes['my_assignments']['to_start'], 'chamados atribuídos a você');
            $card('assignments_in_progress', 'Em andamento', $secoes['my_assignments']['in_progress'], 'atendimentos seus em curso');
            $card('assignments_completed_month', 'Concluídos no mês', $secoes['my_assignments']['completed_this_month'], 'atendimentos finalizados por você');
        }

        if (isset($secoes['occurrence_queue'])) {
            $fila = $secoes['occurrence_queue'];
            $card('open_occurrences', 'Ocorrências abertas', $fila['open_total'], "{$fila['awaiting_analysis']} aguardando análise");
        }

        if (isset($secoes['reservations'])) {
            $card('reservations_awaiting_approval', 'Reservas a aprovar', $secoes['reservations']['awaiting_approval'], 'pedidos pendentes');
        }

        if (isset($secoes['units'])) {
            $card('occupied_units', 'Unidades ocupadas', $secoes['units']['occupied'], "de {$secoes['units']['active']} ativas");
        }

        if (isset($secoes['people'])) {
            $card('active_residents', 'Moradores', $secoes['people']['residents_active'], 'moradores ativos');
        }

        if (isset($secoes['month'])) {
            $media = $secoes['month']['occurrences']['average_resolution_hours'];
            $card('completed_this_month', 'Concluídas no mês', $secoes['month']['occurrences']['completed'], $media === null
                ? 'nenhuma conclusão no mês'
                : 'tempo médio de '.str_replace('.', ',', (string) $media).' h');
        }

        if (isset($secoes['notices'])) {
            $rascunhos = $secoes['notices']['drafts'] ?? null;
            $card('published_notices', 'Comunicados', $secoes['notices']['published'], $rascunhos ? "publicados · {$rascunhos} em rascunho" : 'publicados');
        }

        return $cards;
    }

    /**
     * Os chamados do próprio morador.
     *
     * @return array{open: int, by_status: list<array{status: string, label: string, total: int}>, latest: list<array<string, mixed>>}
     */
    private static function myOccurrences(User $user): array
    {
        $totais = Occurrence::query()
            ->where('resident_id', $user->id)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentes = Occurrence::query()
            ->where('resident_id', $user->id)
            ->select(['id', 'title', 'status', 'occurrence_type_id', 'updated_at'])
            ->with('occurrenceType:id,name')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::LISTA)
            ->get();

        return [
            'open' => self::naoFinais($totais),
            'by_status' => self::porStatusDeOcorrencia($totais),
            'latest' => $recentes->map(fn (Occurrence $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'occurrence_type' => $o->occurrenceType?->only(['id', 'name']),
                'updated_at' => $o->updated_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * As próximas reservas do próprio morador, de hoje em diante.
     *
     * @return array{upcoming_total: int, pending: int, upcoming: list<array<string, mixed>>}
     */
    private static function myReservations(User $user, CarbonImmutable $agora): array
    {
        $futuras = Reservation::query()
            ->where('resident_id', $user->id)
            ->where('date', '>=', $agora->toDateString())
            ->whereIn('status', ReservationStatus::blocking());

        $totais = (clone $futuras)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $proximas = $futuras
            ->select(['id', 'common_area_id', 'date', 'start_time', 'end_time', 'status'])
            ->with('commonArea:id,name')
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->limit(self::LISTA)
            ->get();

        return [
            'upcoming_total' => (int) $totais->sum(),
            'pending' => (int) ($totais[ReservationStatus::Pending->value] ?? 0),
            'upcoming' => $proximas->map(fn (Reservation $r) => self::linhaDeReserva($r))->all(),
        ];
    }

    /**
     * A fila do funcionário: o que lhe foi atribuído e ainda não terminou,
     * do mais antigo para o mais novo — é a ordem em que ele deveria atender.
     *
     * @return array{to_start: int, in_progress: int, completed_this_month: int, queue: list<array<string, mixed>>}
     */
    private static function myAssignments(User $user, CarbonImmutable $agora): array
    {
        $totais = Occurrence::query()
            ->where('assigned_employee_id', $user->id)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $concluidosNoMes = Occurrence::query()
            ->where('assigned_employee_id', $user->id)
            ->where('status', OccurrenceStatus::Completed->value)
            ->where('completed_at', '>=', $agora->startOfMonth())
            ->count();

        $fila = Occurrence::query()
            ->where('assigned_employee_id', $user->id)
            ->whereIn('status', [OccurrenceStatus::Assigned->value, OccurrenceStatus::InProgress->value])
            ->select(['id', 'title', 'status', 'occurrence_type_id', 'unit_id', 'created_at'])
            ->with(['occurrenceType:id,name,employee_can_close', 'unit:id,code'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::LISTA)
            ->get();

        return [
            'to_start' => (int) ($totais[OccurrenceStatus::Assigned->value] ?? 0),
            'in_progress' => (int) ($totais[OccurrenceStatus::InProgress->value] ?? 0),
            'completed_this_month' => $concluidosNoMes,
            'queue' => $fila->map(fn (Occurrence $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'occurrence_type' => $o->occurrenceType?->only(['id', 'name', 'employee_can_close']),
                'unit' => $o->unit?->only(['id', 'code']),
                'waiting_days' => self::diasDesde($o->created_at, $agora),
            ])->all(),
        ];
    }

    /**
     * A fila do condomínio inteiro, para quem analisa, encaminha ou dá baixa:
     * quanto há em cada etapa e quais chamados esperam há mais tempo.
     *
     * @return array<string, mixed>
     */
    private static function occurrenceQueue(CarbonImmutable $agora): array
    {
        $totais = Occurrence::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $maisAntigos = Occurrence::query()
            ->whereNotIn('status', [OccurrenceStatus::Completed->value, OccurrenceStatus::Cancelled->value])
            ->select(['id', 'title', 'status', 'occurrence_type_id', 'resident_id', 'unit_id', 'assigned_employee_id', 'created_at'])
            ->with(['occurrenceType:id,name', 'resident:id,name', 'unit:id,code', 'assignedEmployee:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::LISTA)
            ->get();

        return [
            'open_total' => self::naoFinais($totais),
            'awaiting_analysis' => (int) ($totais[OccurrenceStatus::Open->value] ?? 0),
            'awaiting_assignment' => (int) ($totais[OccurrenceStatus::Analysis->value] ?? 0),
            'assigned' => (int) ($totais[OccurrenceStatus::Assigned->value] ?? 0),
            'in_progress' => (int) ($totais[OccurrenceStatus::InProgress->value] ?? 0),
            'oldest_waiting' => $maisAntigos->map(fn (Occurrence $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'occurrence_type' => $o->occurrenceType?->only(['id', 'name']),
                'resident' => $o->resident?->only(['id', 'name']),
                'unit' => $o->unit?->only(['id', 'code']),
                'assigned_employee' => $o->assignedEmployee?->only(['id', 'name']),
                'waiting_days' => self::diasDesde($o->created_at, $agora),
            ])->all(),
        ];
    }

    /**
     * A agenda das áreas comuns para quem aprova: o que espera decisão e o
     * que vem pela frente.
     *
     * @return array<string, mixed>
     */
    private static function reservations(CarbonImmutable $agora): array
    {
        $hoje = $agora->toDateString();

        $totais = Reservation::query()
            ->where('date', '>=', $hoje)
            ->toBase()
            // `date` vai qualificado: é palavra-chave no MySQL.
            ->selectRaw('status, sum(case when reservations.date = ? then 1 else 0 end) as today, sum(case when reservations.date <= ? then 1 else 0 end) as week, count(*) as total', [$hoje, $agora->addDays(6)->toDateString()])
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $aprovadas = $totais[ReservationStatus::Approved->value] ?? null;
        $pendentes = $totais[ReservationStatus::Pending->value] ?? null;

        $proximas = Reservation::query()
            ->where('date', '>=', $hoje)
            ->whereIn('status', ReservationStatus::blocking())
            ->select(['id', 'common_area_id', 'resident_id', 'date', 'start_time', 'end_time', 'status'])
            ->with(['commonArea:id,name', 'resident:id,name,unit_id', 'resident.unit:id,code'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->limit(self::LISTA)
            ->get();

        return [
            'awaiting_approval' => (int) ($pendentes->total ?? 0),
            'today' => (int) ($aprovadas->today ?? 0),
            'next_7_days' => (int) ($aprovadas->week ?? 0) + (int) ($pendentes->week ?? 0),
            'upcoming' => $proximas->map(fn (Reservation $r) => self::linhaDeReserva($r) + [
                'resident' => $r->resident ? [
                    'id' => $r->resident->id,
                    'name' => $r->resident->name,
                    'unit' => $r->resident->unit?->only(['id', 'code']),
                ] : null,
            ])->all(),
        ];
    }

    /**
     * Ocupação das unidades: ativa com morador vinculado é ocupada; ativa sem
     * ninguém, vaga.
     *
     * @return array{total: int, active: int, inactive: int, occupied: int, vacant: int}
     */
    private static function units(): array
    {
        $porStatus = Unit::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $ocupadas = Unit::query()
            ->where('status', UnitStatus::Active->value)
            ->whereHas('activeOccupancies')
            ->count();

        $ativas = (int) ($porStatus[UnitStatus::Active->value] ?? 0);

        return [
            'total' => (int) $porStatus->sum(),
            'active' => $ativas,
            'inactive' => (int) ($porStatus[UnitStatus::Inactive->value] ?? 0),
            'occupied' => $ocupadas,
            'vacant' => $ativas - $ocupadas,
        ];
    }

    /**
     * Quem usa o sistema, para quem cadastra pessoas. `first_access_pending`
     * são as contas criadas que ainda não trocaram a senha temporária — a
     * administração precisa saber a quem cobrar o primeiro acesso.
     *
     * @return array{residents_active: int, employees_active: int, syndics_active: int, inactive_accounts: int, first_access_pending: int}
     */
    private static function people(): array
    {
        $linhas = User::query()
            ->toBase()
            ->selectRaw('role, status, must_change_password, count(*) as total')
            ->groupBy('role', 'status', 'must_change_password')
            ->get();

        $ativos = fn (UserRole $role) => (int) $linhas
            ->where('role', $role->value)
            ->where('status', UserStatus::Active->value)
            ->sum('total');

        return [
            'residents_active' => $ativos(UserRole::Resident),
            'employees_active' => $ativos(UserRole::Employee),
            'syndics_active' => $ativos(UserRole::Syndic),
            'inactive_accounts' => (int) $linhas->where('status', UserStatus::Inactive->value)->sum('total'),
            'first_access_pending' => (int) $linhas
                ->where('status', UserStatus::Active->value)
                ->filter(fn ($linha) => (bool) $linha->must_change_password)
                ->sum('total'),
        ];
    }

    /**
     * O mês corrente, com a mesma conta dos relatórios: ocorrências abertas no
     * mês e reservas com data no mês.
     *
     * @return array{from: string, to: string, occurrences: array<string, mixed>, reservations: array<string, int>}
     */
    private static function month(CarbonImmutable $agora): array
    {
        $inicio = $agora->startOfMonth();
        $fim = $agora->endOfMonth();

        return [
            'from' => $inicio->toDateString(),
            'to' => $fim->toDateString(),
            'occurrences' => BuildOccurrenceReportAction::periodSummary($inicio, $fim),
            'reservations' => BuildReservationReportAction::periodSummary($inicio->toDateString(), $fim->toDateString()),
        ];
    }

    /**
     * O mural: os comunicados publicados mais recentes. Quem publica vê
     * também quantos rascunhos estão parados.
     *
     * @return array<string, mixed>
     */
    private static function notices(User $user): array
    {
        $porStatus = Notice::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $recentes = Notice::query()
            ->where('status', NoticeStatus::Published->value)
            ->select(['id', 'title', 'published_at'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $secao = [
            'published' => (int) ($porStatus[NoticeStatus::Published->value] ?? 0),
            'latest' => $recentes->map(fn (Notice $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'published_at' => $n->published_at?->toIso8601String(),
            ])->all(),
        ];

        // O rascunho só existe para quem publica (RN18).
        if ($user->hasUseCase(UseCase::PublishNotices)) {
            $secao['drafts'] = (int) ($porStatus[NoticeStatus::Draft->value] ?? 0);
        }

        return $secao;
    }

    /**
     * As últimas ações registradas na auditoria, para quem pode ver a
     * auditoria. Só a descrição, quem fez e quando: os dados gravados junto
     * podem ter informação pessoal e ficam no relatório de auditoria.
     *
     * @return list<array{id: int, description: string, user: array{id: int, name: string}|null, created_at: string|null}>
     */
    private static function recentActivity(): array
    {
        return Log::query()
            ->select(['id', 'user_id', 'description', 'created_at'])
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(self::LISTA)
            ->get()
            ->map(fn (Log $log) => [
                'id' => $log->id,
                'description' => $log->description,
                'user' => $log->user?->only(['id', 'name']),
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function linhaDeReserva(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'date' => $reservation->date?->toDateString(),
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'full_day' => $reservation->isFullDay(),
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'common_area' => $reservation->commonArea?->only(['id', 'name']),
        ];
    }

    /**
     * @param  Collection<string, int>  $totais
     */
    private static function naoFinais($totais): int
    {
        return (int) collect(OccurrenceStatus::cases())
            ->reject(fn (OccurrenceStatus $s) => $s->isFinal())
            ->sum(fn (OccurrenceStatus $s) => $totais[$s->value] ?? 0);
    }

    /**
     * @param  Collection<string, int>  $totais
     * @return list<array{status: string, label: string, total: int}>
     */
    private static function porStatusDeOcorrencia($totais): array
    {
        return array_map(fn (OccurrenceStatus $s) => [
            'status' => $s->value,
            'label' => $s->label(),
            'total' => (int) ($totais[$s->value] ?? 0),
        ], OccurrenceStatus::cases());
    }

    private static function diasDesde(?\DateTimeInterface $data, CarbonImmutable $agora): int
    {
        return $data ? (int) floor(CarbonImmutable::instance($data)->diffInDays($agora)) : 0;
    }
}
