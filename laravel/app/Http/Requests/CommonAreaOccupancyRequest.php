<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RN14 — period consulted in the anonymised agenda of a common area.
 */
class CommonAreaOccupancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Informe a data inicial da consulta.',
            'end_date.required' => 'Informe a data final da consulta.',
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ];
    }
}
