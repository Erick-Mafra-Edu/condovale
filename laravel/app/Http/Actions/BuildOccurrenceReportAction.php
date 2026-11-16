<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RF06 / UC20 — o relatório de ocorrências e manutenções do condomínio.
 *
 * O período é o das ocorrências **abertas** nele. Os indicadores (totais por
 * status, por tipo, tempo médio) olham o período inteiro; o tipo e o status
 * filtram só a lista. É o mesmo comportamento da tela de relatório: trocar a
 * aba de "Abertos" para "Concluídos" muda a tabela, não o painel de cima.
 *
 * Todas as contas saem de GROUP BY no banco, em número fixo de consultas, e a
 * lista sai paginada com as relações carregadas de uma vez. É isso que mantém
 * o relatório dentro dos dois segundos da RNF01 quando o condomínio acumula
 * anos de chamados: nada aqui cresce em consultas com o número de linhas.
 */
class BuildOccurrenceReportAction
{
    /**
     * Das pessoas saem id e nome; da unidade, o código. O relatório é da
     * administração, mas e-mail e documento não ajudam a ler um chamado.
     */
    private const RELACOES = [
        'occurrenceType:id,name',
        'resident:id,name',
        'assignedEmployee:id,name',
        'unit:id,code',
    ];

    private const COLUNAS = [
        'id',
        'title',
        'description',
        'occurrence_type_id',
        'resident_id',
        'unit_id',
        'assigned_employee_id',
        'status',
        'resolved',
        'conclusion',
        'created_at',
        'completed_at',
    ];

    /**
     * @param  array{from: string, to: string, occurrence_type_id?: int|null, status?: string|null, page?: int|null, limit?: int|null, export?: bool}  $filters
     * @return array<string, mixed>
     */
    public static function execute(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();

        // Uma consulta só alimenta o resumo e a contagem por status.
        $contagens = self::inPeriod($from, $to)
            ->toBase()
            ->selectRaw('status, resolved, count(*) as total')
            ->groupBy('status', 'resolved')
            ->get();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => self::summary($contagens, $from, $to),
            'by_status' => self::byStatus($contagens),
            'by_type' => self::byType($from, $to),
            'occurrences' => self::rows($from, $to, $filters),
        ];
    }

    /**
     * Abertas no período, por data de abertura.
     *
     * `whereBetween` em vez de `whereDate`: a função em volta da coluna
     * impediria o banco de usar o índice de `created_at`.
     */
    private static function inPeriod(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Occurrence::query()->whereBetween('occurrences.created_at', [$from, $to]);
    }

    /**
     * @param  Collection<int, object{status: string, resolved: int|null, total: int}>  $contagens
     * @return array{total: int, open: int, completed: int, cancelled: int, resolved: int, unresolved: int, average_resolution_hours: float|null}
     */
    private static function summary(Collection $contagens, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $porStatus = fn (OccurrenceStatus $status) => (int) $contagens->where('status', $status->value)->sum('total');
        $concluidas = $contagens->where('status', OccurrenceStatus::Completed->value);

        $total = (int) $contagens->sum('total');
        $finais = $porStatus(OccurrenceStatus::Completed) + $porStatus(OccurrenceStatus::Cancelled);

        return [
            'total' => $total,
            'open' => $total - $finais,
            'completed' => $porStatus(OccurrenceStatus::Completed),
            'cancelled' => $porStatus(OccurrenceStatus::Cancelled),
            // Encerrar não é resolver: o síndico precisa separar o problema
            // que acabou do que só foi fechado. Concluída sem o campo
            // preenchido conta como resolvida, que é o padrão da finalização.
            'resolved' => (int) $concluidas->filter(fn ($linha) => $linha->resolved === null || (bool) $linha->resolved)->sum('total'),
            'unresolved' => (int) $concluidas->filter(fn ($linha) => $linha->resolved !== null && ! (bool) $linha->resolved)->sum('total'),
            'average_resolution_hours' => self::averageResolutionHours($from, $to),
        ];
    }

    /**
     * Da abertura à conclusão, em horas, só das concluídas.
     *
     * A diferença entre datas é calculada aqui, e não no SQL, porque cada
     * banco tem a própria função para isso (SQLite no desenvolvimento, MySQL
     * em produção) e uma das duas ficaria sem teste. São duas colunas por
     * chamado concluído; mesmo um ano de chamados é pouca memória.
     */
    private static function averageResolutionHours(CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $datas = self::inPeriod($from, $to)
            ->where('status', OccurrenceStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->toBase()
            ->get(['created_at', 'completed_at']);

        if ($datas->isEmpty()) {
            return null;
        }

        $segundos = $datas->avg(fn ($linha) => strtotime($linha->completed_at) - strtotime($linha->created_at));

        return round($segundos / 3600, 1);
    }

    /**
     * Todos os status aparecem, inclusive os que ficaram em zero: o gráfico e
     * a planilha precisam das mesmas colunas em qualquer período.
     *
     * @param  Collection<int, object{status: string, resolved: int|null, total: int}>  $contagens
     * @return list<array{status: string, label: string, total: int}>
     */
    private static function byStatus(Collection $contagens): array
    {
        return array_map(fn (OccurrenceStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'total' => (int) $contagens->where('status', $status->value)->sum('total'),
        ], OccurrenceStatus::cases());
    }

    /**
     * Só os tipos que tiveram chamado no período, do mais frequente para o
     * menos. É também a lista que alimenta o filtro de tipo da tela.
     *
     * @return list<array{occurrence_type_id: int, name: string, total: int}>
     */
    private static function byType(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return self::inPeriod($from, $to)
            ->toBase()
            ->join('occurrence_types', 'occurrence_types.id', '=', 'occurrences.occurrence_type_id')
            ->selectRaw('occurrence_types.id as occurrence_type_id, occurrence_types.name as name, count(*) as total')
            ->groupBy('occurrence_types.id', 'occurrence_types.name')
            ->orderByDesc('total')
            ->orderBy('occurrence_types.name')
            ->get()
            ->map(fn ($linha) => [
                'occurrence_type_id' => (int) $linha->occurrence_type_id,
                'name' => $linha->name,
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * A lista, com tipo e status aplicados.
     *
     * Na exportação vem tudo (RN12), até o teto de `reports.occurrences.max_export_rows`.
     * A ordem é sempre a mesma — mais recente primeiro, com o id desempatando —
     * para que paginar não repita nem pule chamado.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    private static function rows(CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $query = self::inPeriod($from, $to)
            ->when($filters['occurrence_type_id'] ?? null, fn (Builder $q, int $tipo) => $q->where('occurrence_type_id', $tipo))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->select(self::COLUNAS)
            ->with(self::RELACOES)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filters['export'] ?? false) {
            return self::exportRows($query);
        }

        $pagina = $query->paginate($filters['limit'] ?? 10, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'data' => self::serialize($pagina->getCollection()),
            'total' => $pagina->total(),
            'page' => $pagina->currentPage(),
            'per_page' => $pagina->perPage(),
            'last_page' => $pagina->lastPage(),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    private static function exportRows(Builder $query): array
    {
        $teto = (int) config('reports.occurrences.max_export_rows');
        $total = (clone $query)->count();

        if ($total > $teto) {
            throw BusinessRuleException::unprocessable(
                "O período tem {$total} ocorrências, acima do máximo de {$teto} por exportação. Reduza o período ou filtre por tipo ou status."
            );
        }

        return [
            'data' => self::serialize($query->get()),
            'total' => $total,
            'page' => 1,
            'per_page' => $total,
            'last_page' => 1,
        ];
    }

    /**
     * @param  iterable<Occurrence>  $occurrences
     * @return list<array<string, mixed>>
     */
    private static function serialize(iterable $occurrences): array
    {
        $linhas = [];

        foreach ($occurrences as $occurrence) {
            $linhas[] = $occurrence->toArray() + ['status_label' => $occurrence->status->label()];
        }

        return $linhas;
    }
}
