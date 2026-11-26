<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * RF06 / UC20 — o relatório de reservas das áreas comuns.
 *
 * O período é o da **data da reserva**, não o de quando ela foi pedida: o
 * síndico quer saber como os espaços foram usados num mês, e uma reserva para
 * dezembro pedida em outubro é uso de dezembro.
 *
 * Igual ao relatório de ocorrências, os indicadores olham o período inteiro, e
 * área e status filtram só a lista. As contas saem de GROUP BY, em número fixo
 * de consultas, para que o relatório não cresça em consultas com os anos de
 * reservas (RNF01).
 */
class BuildReservationReportAction
{
    /**
     * Do morador saem nome e unidade, que é o que identifica quem usou o
     * espaço; e-mail e documento não entram.
     */
    private const RELACOES = [
        'commonArea:id,name',
        'resident:id,name,unit_id',
        'resident.unit:id,code',
    ];

    private const COLUNAS = [
        'id',
        'common_area_id',
        'resident_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'rejection_reason',
        'created_at',
    ];

    /**
     * @param  array{from: string, to: string, common_area_id?: int|null, status?: string|null, page?: int|null, limit?: int|null, export?: bool}  $filters
     * @return array<string, mixed>
     */
    public static function execute(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from'])->toDateString();
        $to = CarbonImmutable::parse($filters['to'])->toDateString();

        $totais = self::totalsByStatus($from, $to);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'summary' => self::summary($totais),
            'by_status' => self::byStatus($totais),
            'by_area' => self::byArea($from, $to),
            'reservations' => self::rows($from, $to, $filters),
        ];
    }

    /**
     * O resumo de um período, sem lista — é o que o painel mostra do mês, com
     * a mesma conta do relatório.
     *
     * @return array{total: int, pending: int, approved: int, rejected: int, cancelled: int}
     */
    public static function periodSummary(string $from, string $to): array
    {
        return self::summary(self::totalsByStatus($from, $to));
    }

    private static function totalsByStatus(string $from, string $to): Collection
    {
        return self::inPeriod($from, $to)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    /**
     * A coluna `date` guarda só o dia, então a comparação é direta, com os dois
     * extremos inclusos, e usa o índice de `date`.
     */
    private static function inPeriod(string $from, string $to): Builder
    {
        return Reservation::query()->whereBetween('reservations.date', [$from, $to]);
    }

    /**
     * @param  Collection<string, int>  $totais
     * @return array{total: int, pending: int, approved: int, rejected: int, cancelled: int}
     */
    private static function summary(Collection $totais): array
    {
        $resumo = ['total' => (int) $totais->sum()];

        foreach (ReservationStatus::cases() as $status) {
            $resumo[$status->value] = (int) ($totais[$status->value] ?? 0);
        }

        return $resumo;
    }

    /**
     * Todos os status aparecem, mesmo os zerados: o gráfico e a planilha
     * precisam das mesmas colunas em qualquer período.
     *
     * @param  Collection<string, int>  $totais
     * @return list<array{status: string, label: string, total: int}>
     */
    private static function byStatus(Collection $totais): array
    {
        return array_map(fn (ReservationStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'total' => (int) ($totais[$status->value] ?? 0),
        ], ReservationStatus::cases());
    }

    /**
     * Todas as áreas comuns, inclusive as que não tiveram reserva no período.
     *
     * Diferente do relatório de ocorrências, que só lista os tipos usados, aqui
     * a área sem uso é informação: o salão que ninguém reservou num mês é
     * exatamente o que o síndico precisa ver. Uma consulta só, com LEFT JOIN, e
     * a contagem por status montada aqui.
     *
     * @return list<array{common_area_id: int, name: string, total: int, pending: int, approved: int, rejected: int, cancelled: int}>
     */
    private static function byArea(string $from, string $to): array
    {
        $linhas = CommonArea::query()
            ->toBase()
            ->leftJoin('reservations', function (JoinClause $join) use ($from, $to) {
                $join->on('reservations.common_area_id', '=', 'common_areas.id')
                    ->whereBetween('reservations.date', [$from, $to]);
            })
            ->selectRaw('common_areas.id as common_area_id, common_areas.name as name, reservations.status as status, count(reservations.id) as total')
            ->groupBy('common_areas.id', 'common_areas.name', 'reservations.status')
            ->get();

        return $linhas
            ->groupBy('common_area_id')
            ->map(function (Collection $daArea) {
                $area = [
                    'common_area_id' => (int) $daArea->first()->common_area_id,
                    'name' => $daArea->first()->name,
                    'total' => (int) $daArea->sum('total'),
                ];

                foreach (ReservationStatus::cases() as $status) {
                    $area[$status->value] = (int) $daArea->where('status', $status->value)->sum('total');
                }

                return $area;
            })
            ->sortBy([['total', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * A lista, com área e status aplicados. Mais recente primeiro — dia,
     * horário e id, para que paginar não repita nem pule reserva.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    private static function rows(string $from, string $to, array $filters): array
    {
        $query = self::inPeriod($from, $to)
            ->when($filters['common_area_id'] ?? null, fn (Builder $q, int $area) => $q->where('common_area_id', $area))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->select(self::COLUNAS)
            ->with(self::RELACOES)
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->orderByDesc('id');

        return PaginateReportAction::execute(
            $query,
            $filters,
            (int) config('reports.reservations.max_export_rows'),
            fn (Reservation $reservation) => $reservation->toArray() + [
                'status_label' => $reservation->status->label(),
                // Diária não tem horário: a reserva ocupa o dia inteiro da área.
                'full_day' => $reservation->isFullDay(),
            ],
            'reservas',
        );
    }
}
