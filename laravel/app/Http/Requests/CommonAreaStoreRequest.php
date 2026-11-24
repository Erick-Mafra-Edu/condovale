<?php

namespace App\Http\Requests;

use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * AreaComum — name, description, the allowed hours and whether it accepts
 * reservations, plus the reservation rules the calendar applies.
 *
 * Opening and closing time (horarioInicio and horarioFim) are required:
 * without them there is no grid to book from, and the reservation creation
 * refuses the area. Closing accepts "24:00" — or "00:00" — for midnight.
 */
class CommonAreaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120|unique:common_areas,name',
            'description' => 'nullable|string|max:255',
            'image_url' => 'nullable|url:http,https|max:255',
            'capacity' => 'nullable|integer|min:1',
            'opening_time' => 'required|date_format:H:i',
            'closing_time' => 'required|'.TimeUtil::END_TIME_RULE,
            'max_reservation_minutes' => 'nullable|integer|min:30|multiple_of:30',
            'requires_approval' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:available,unavailable',
        ];
    }

    public function messages(): array
    {
        return self::mensagens();
    }

    /**
     * The order of the hours is checked in minutes, not by `after`: "24:00"
     * and "00:00" as closing mean midnight, which a date comparison reads as
     * the start of the day.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['opening_time', 'closing_time'])) {
                    return;
                }

                $problem = CommonArea::hoursProblem($this->input('opening_time'), $this->input('closing_time'));

                if ($problem !== null) {
                    $validator->errors()->add('closing_time', $problem);
                }
            },
        ];
    }

    /**
     * Shared with the update request, so both forms speak the same words.
     *
     * @return array<string, string>
     */
    public static function mensagens(): array
    {
        return [
            'name.required' => 'Informe o nome da área comum.',
            'name.max' => 'O nome da área comum pode ter até 120 caracteres.',
            'name.unique' => 'Já existe uma área comum com este nome.',
            'description.max' => 'A descrição pode ter até 255 caracteres.',
            'image_url.url' => 'Informe o endereço da imagem começando por http:// ou https://.',
            'image_url.max' => 'O endereço da imagem pode ter até 255 caracteres.',
            'capacity.integer' => 'A capacidade deve ser um número inteiro.',
            'capacity.min' => 'A capacidade deve ser de pelo menos 1 pessoa.',
            'opening_time.required' => 'Informe o horário de abertura.',
            'opening_time.date_format' => 'Informe o horário de abertura no formato HH:MM.',
            'closing_time.required' => 'Informe o horário de fechamento.',
            'closing_time.regex' => 'Informe o horário de fechamento no formato HH:MM, ou 24:00 para meia-noite.',
            'max_reservation_minutes.integer' => 'A duração máxima deve ser um número inteiro de minutos.',
            'max_reservation_minutes.min' => 'A duração máxima deve ser de pelo menos 30 minutos.',
            'max_reservation_minutes.multiple_of' => 'A duração máxima deve ser múltipla de 30 minutos, o tamanho dos blocos de reserva.',
            'requires_approval.boolean' => 'Informe se a reserva exige aprovação com verdadeiro ou falso.',
            'status.in' => 'A situação deve ser available (disponível) ou unavailable (indisponível).',
        ];
    }
}
