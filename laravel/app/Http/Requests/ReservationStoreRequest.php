<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF03 — the resident informs area, date and, optionally, the period.
 *
 * The resident is never part of the payload: the author is the session user.
 */
class ReservationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'common_area_id' => 'required|integer|exists:common_areas,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'common_area_id.required' => 'Selecione uma área comum.',
            'common_area_id.exists' => 'A área comum informada não existe.',
            'date.required' => 'Informe a data da reserva.',
            'date.date_format' => 'Informe a data no formato AAAA-MM-DD.',
            'start_time.date_format' => 'Informe o horário inicial no formato HH:MM.',
            'end_time.date_format' => 'Informe o horário final no formato HH:MM.',
        ];
    }
}
