<?php

namespace App\Http\Controllers;

use App\Http\Actions\BuildOccurrenceReportAction;
use App\Http\Requests\OccurrenceReportRequest;
use App\Http\Utils\SanitizeUtil;
use App\Services\MessageService;
use Carbon\CarbonImmutable;

/**
 * RF06 / UC20 — relatórios operacionais do condomínio.
 *
 * Quem chega aqui já passou pelo caso de uso `generate-reports` no grupo de
 * rota (síndico e administração). O relatório cobre o condomínio inteiro, sem
 * o recorte por pessoa das telas de acompanhamento: é para isso que ele existe.
 */
class ReportController extends Controller
{
    public function occurrences(OccurrenceReportRequest $request)
    {
        $item = (object) $request->validated();

        $filters = [
            'from' => $item->from,
            'to' => $item->to,
            'occurrence_type_id' => isset($item->occurrence_type_id) ? SanitizeUtil::sanitizeInt($item->occurrence_type_id) : null,
            'status' => $item->status ?? null,
            'page' => $item->page ?? 1,
            'limit' => $item->limit ?? 10,
            'export' => $request->boolean('export'),
        ];

        try {
            $report = BuildOccurrenceReportAction::execute($filters);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        $de = CarbonImmutable::parse($item->from)->format('d/m/Y');
        $ate = CarbonImmutable::parse($item->to)->format('d/m/Y');

        return MessageService::success("Relatório de ocorrências de {$de} a {$ate}.", $report);
    }
}
