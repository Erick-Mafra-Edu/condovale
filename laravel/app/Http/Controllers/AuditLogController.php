<?php

namespace App\Http\Controllers;

use App\Http\Actions\BuildAuditReportAction;
use App\Http\Requests\AuditLogRequest;
use App\Http\Utils\SanitizeUtil;
use App\Services\MessageService;
use Carbon\CarbonImmutable;

/**
 * UC-T03 / RNF04 — a consulta da trilha de auditoria.
 *
 * Quem chega aqui já passou pelo caso de uso `view-audit-reports` no grupo de
 * rota: só a administração (RN16). A trilha só é lida; não há rota que altere
 * ou apague um registro.
 */
class AuditLogController extends Controller
{
    public function index(AuditLogRequest $request)
    {
        $item = (object) $request->validated();

        $filters = [
            'from' => $item->from,
            'to' => $item->to,
            'page' => (int) ($item->page ?? 1),
            'limit' => (int) ($item->limit ?? 10),
            'export' => $request->boolean('export'),
            'entity' => $item->entity ?? null,
            'subject_id' => isset($item->entity_id) ? SanitizeUtil::sanitizeInt($item->entity_id) : null,
            'user_id' => isset($item->user_id) ? SanitizeUtil::sanitizeInt($item->user_id) : null,
            'role' => $item->role ?? null,
            'action' => $item->action ?? null,
        ];

        try {
            $report = BuildAuditReportAction::execute($filters);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        $de = CarbonImmutable::parse($item->from)->format('d/m/Y');
        $ate = CarbonImmutable::parse($item->to)->format('d/m/Y');

        return MessageService::success("Relatório de auditoria de {$de} a {$ate}.", $report);
    }
}
