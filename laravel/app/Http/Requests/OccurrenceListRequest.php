<?php

namespace App\Http\Requests;

use App\Enums\OccurrenceKind;

/**
 * The occurrence listing: the listing contract plus `kind`, which separates
 * complaints (UC7) from maintenance requests (UC6). The nature belongs to the
 * type, in another table, so the generic `filters` cannot reach it.
 */
class OccurrenceListRequest extends DefaultPaginationRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'kind' => 'nullable|in:'.OccurrenceKind::values(),
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'kind.in' => 'Natureza inválida. Use manutenção (maintenance) ou denúncia (complaint).',
        ];
    }
}
