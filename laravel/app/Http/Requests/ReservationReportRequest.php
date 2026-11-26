<?php

namespace App\Http\Requests;

use App\Enums\ReservationStatus;

/**
 * RF06 — a consulta do relatório de reservas das áreas comuns.
 *
 * Os nomes seguem o `ReservationReportQuery` do frontend: período e status,
 * mais a área comum. Período, página e exportação vêm de ReportRequest.
 */
class ReservationReportRequest extends ReportRequest
{
    public function rules(): array
    {
        $status = implode(',', array_map(fn (ReservationStatus $s) => $s->value, ReservationStatus::cases()));

        return parent::rules() + [
            'common_area_id' => 'nullable|integer|exists:common_areas,id',
            'status' => 'nullable|in:'.$status,
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'common_area_id.integer' => 'A área comum deve ser informada pelo código.',
            'common_area_id.exists' => 'A área comum informada não existe.',
            'status.in' => 'O status informado não existe.',
        ];
    }
}
