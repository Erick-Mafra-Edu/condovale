<?php

namespace App\Http\Requests;

use App\Enums\OccurrenceKind;
use App\Enums\OccurrenceStatus;

/**
 * RF06 — a consulta do relatório de ocorrências e manutenções.
 *
 * Os nomes seguem o `ReportQuery` do frontend: período, tipo, status e
 * paginação. Período, página e exportação vêm de ReportRequest.
 */
class OccurrenceReportRequest extends ReportRequest
{
    public function rules(): array
    {
        $status = implode(',', array_map(fn (OccurrenceStatus $s) => $s->value, OccurrenceStatus::cases()));

        return parent::rules() + [
            'occurrence_type_id' => 'nullable|integer|exists:occurrence_types,id',
            'status' => 'nullable|in:'.$status,
            // UC7 — só denúncias (complaint) ou só manutenção (maintenance).
            'kind' => 'nullable|in:'.OccurrenceKind::values(),
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'occurrence_type_id.integer' => 'O tipo de ocorrência deve ser informado pelo código.',
            'occurrence_type_id.exists' => 'O tipo de ocorrência informado não existe.',
            'status.in' => 'O status informado não existe.',
            'kind.in' => 'Natureza inválida. Use manutenção (maintenance) ou denúncia (complaint).',
        ];
    }
}
