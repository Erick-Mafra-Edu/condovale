<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * RN14 — period consulted in the agenda of a common area, by the occupancy and
 * by the availability queries.
 */
class CommonAreaOccupancyRequest extends FormRequest
{
    /**
     * Two months cover the calendar of the reservation screen with room to
     * spare, and keep a single request from walking years of agenda.
     */
    public const MAX_DAYS = 62;

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
            'start_date.date_format' => 'Informe a data inicial no formato AAAA-MM-DD.',
            'end_date.required' => 'Informe a data final da consulta.',
            'end_date.date_format' => 'Informe a data final no formato AAAA-MM-DD.',
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = Carbon::createFromFormat('!Y-m-d', $this->input('start_date'));
                $end = Carbon::createFromFormat('!Y-m-d', $this->input('end_date'));

                if ((int) $start->diffInDays($end) + 1 > self::MAX_DAYS) {
                    $validator->errors()->add(
                        'end_date',
                        'Consulte no máximo '.self::MAX_DAYS.' dias por vez.'
                    );
                }
            },
        ];
    }
}
