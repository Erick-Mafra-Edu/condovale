<?php

namespace App\Http\Controllers;

use App\Http\Actions\BuildOccurrenceReportAction;
use App\Http\Actions\BuildReservationReportAction;
use App\Http\Requests\OccurrenceReportRequest;
use App\Http\Requests\ReportRequest;
use App\Http\Requests\ReservationReportRequest;
use App\Http\Utils\SanitizeUtil;
use App\Services\MessageService;
use Carbon\CarbonImmutable;

/**
 * RF06 / UC20 — relatórios operacionais do condomínio.
 *
 * Quem chega aqui já passou pelo caso de uso `generate-reports` no grupo de
 * rota (síndico e administração). Os relatórios cobrem o condomínio inteiro,
 * sem o recorte por pessoa das telas de acompanhamento: é para isso que eles
 * existem.
 */
class ReportController extends Controller
{
    public function occurrences(OccurrenceReportRequest $request)
    {
        $item = (object) $request->validated();

        $filters = self::filtros($request, $item) + [
            'occurrence_type_id' => isset($item->occurrence_type_id) ? SanitizeUtil::sanitizeInt($item->occurrence_type_id) : null,
            'status' => $item->status ?? null,
            'kind' => $item->kind ?? null,
        ];

        try {
            $report = BuildOccurrenceReportAction::execute($filters);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        return MessageService::success('Relatório de ocorrências '.self::periodo($item).'.', $report);
    }

    public function reservations(ReservationReportRequest $request)
    {
        $item = (object) $request->validated();

        $filters = self::filtros($request, $item) + [
            'common_area_id' => isset($item->common_area_id) ? SanitizeUtil::sanitizeInt($item->common_area_id) : null,
            'status' => $item->status ?? null,
        ];

        try {
            $report = BuildReservationReportAction::execute($filters);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        return MessageService::success('Relatório de reservas '.self::periodo($item).'.', $report);
    }

    /**
     * Período, paginação e exportação, comuns a todo relatório.
     *
     * @return array{from: string, to: string, page: int, limit: int, export: bool}
     */
    private static function filtros(ReportRequest $request, object $item): array
    {
        return [
            'from' => $item->from,
            'to' => $item->to,
            'page' => (int) ($item->page ?? 1),
            'limit' => (int) ($item->limit ?? 10),
            'export' => $request->boolean('export'),
        ];
    }

    private static function periodo(object $item): string
    {
        $de = CarbonImmutable::parse($item->from)->format('d/m/Y');
        $ate = CarbonImmutable::parse($item->to)->format('d/m/Y');

        return "de {$de} a {$ate}";
    }
}
