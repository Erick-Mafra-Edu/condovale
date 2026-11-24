<?php

namespace App\Http\Actions;

use App\Enums\AuditEntity;
use App\Enums\UserRole;
use App\Models\Log;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * UC-T03 / RNF04 / RN16 — o relatório de auditoria: quem fez o quê, em qual
 * registro, quando e com que perfil.
 *
 * Segue o contrato dos relatórios operacionais (RF06): o período limita a
 * consulta e alimenta os indicadores; entidade, registro, responsável, perfil e
 * ação filtram só a lista. As contas saem de GROUP BY, em número fixo de
 * consultas, porque `logs` é a tabela que mais cresce (RNF01).
 */
class BuildAuditReportAction
{
    private const COLUNAS = [
        'id',
        'type_log_id',
        'action',
        'subject_id',
        'user_id',
        'user_role',
        'description',
        'data_log',
        'created_at',
    ];

    /**
     * @param  array{from: string, to: string, entity?: string|null, subject_id?: int|null, user_id?: int|null, role?: string|null, action?: string|null, page?: int|null, limit?: int|null, export?: bool}  $filters
     * @return array<string, mixed>
     */
    public static function execute(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->startOfDay();

        $porPerfil = self::inPeriod($from, $to)
            ->toBase()
            ->selectRaw('user_role, count(*) as total')
            ->groupBy('user_role')
            ->pluck('total', 'user_role');

        $porTipo = self::inPeriod($from, $to)
            ->toBase()
            ->selectRaw('type_log_id, count(*) as total')
            ->groupBy('type_log_id')
            ->pluck('total', 'type_log_id');

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => self::summary($porPerfil),
            'by_role' => self::byRole($porPerfil),
            'by_entity' => self::byEntity($porTipo),
            'logs' => self::rows($from, $to, $filters),
        ];
    }

    /**
     * `created_at` guarda data e hora: o último dia entra inteiro pela
     * comparação com o início do dia seguinte, e a consulta usa o índice.
     */
    private static function inPeriod(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Log::query()
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $to->addDay()->toDateTimeString());
    }

    /**
     * O total e quanto dele foi feito por cada perfil. Registro sem perfil
     * (sem sessão, ou de quem já não existe) entra só no total.
     *
     * @param  Collection<string, int>  $porPerfil
     * @return array<string, int>
     */
    private static function summary(Collection $porPerfil): array
    {
        $resumo = ['total' => (int) $porPerfil->sum()];

        foreach (UserRole::cases() as $role) {
            $resumo[$role->value] = (int) ($porPerfil[$role->value] ?? 0);
        }

        return $resumo;
    }

    /**
     * Todos os perfis aparecem, mesmo os zerados, como no by_status dos
     * outros relatórios.
     *
     * @param  Collection<string, int>  $porPerfil
     * @return list<array{role: string, label: string, total: int}>
     */
    private static function byRole(Collection $porPerfil): array
    {
        return array_map(fn (UserRole $role) => [
            'role' => $role->value,
            'label' => $role->label(),
            'total' => (int) ($porPerfil[$role->value] ?? 0),
        ], UserRole::cases());
    }

    /**
     * @param  Collection<int, int>  $porTipo
     * @return list<array{entity: string, label: string, total: int}>
     */
    private static function byEntity(Collection $porTipo): array
    {
        return array_map(fn (AuditEntity $entity) => [
            'entity' => $entity->value,
            'label' => $entity->label(),
            'total' => (int) collect($entity->typeLogIds())->sum(fn (int $id) => $porTipo[$id] ?? 0),
        ], AuditEntity::cases());
    }

    /**
     * A lista, mais recente primeiro — hora e id, para que paginar não repita
     * nem pule registro. O IP fica de fora: é dado operacional e o model o
     * esconde.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    private static function rows(CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $entity = isset($filters['entity']) ? AuditEntity::from($filters['entity']) : null;

        $query = self::inPeriod($from, $to)
            ->when($entity, fn (Builder $q) => $q->whereIn('type_log_id', $entity->typeLogIds()))
            ->when($filters['subject_id'] ?? null, fn (Builder $q, int $id) => $q->where('subject_id', $id))
            ->when($filters['user_id'] ?? null, fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->where('user_role', $role))
            ->when($filters['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', $action))
            ->select(self::COLUNAS)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return PaginateReportAction::execute(
            $query,
            $filters,
            (int) config('reports.audit.max_export_rows'),
            fn (Log $log) => self::row($log),
            'registros de auditoria',
        );
    }

    /**
     * RN16 — autor, ação, entidade, registro, data e hora e os detalhes. Os
     * nomes batem com o AuditLog do frontend (entity, entity_id, metadata).
     *
     * @return array<string, mixed>
     */
    private static function row(Log $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => $log->auditAction()?->label(),
            'entity' => $log->entity()?->value,
            'entity_label' => $log->entity()?->label(),
            'entity_id' => $log->subject_id,
            'description' => $log->description,
            'user' => $log->user?->only(['id', 'name']),
            'user_role' => $log->user_role,
            'user_role_label' => $log->authorRole()?->label(),
            'metadata' => $log->data_log,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
