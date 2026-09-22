<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query contract of every listing, consumed by HandlePaginationAction.
 */
class DefaultPaginationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attributes' => 'nullable|string',
            'direction' => 'nullable|string',
            'search' => 'nullable|string',
            'limit' => 'nullable|integer',
            'page' => 'nullable|integer',
            'sort' => 'nullable|string',
            'filters' => 'nullable|string',
            'filtersOr' => 'nullable|string',
            'filtersIn' => 'nullable|string',
            'relations' => 'nullable|string',
        ];
    }
}
