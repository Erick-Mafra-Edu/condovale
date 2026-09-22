<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommonAreaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i|after:opening_time',
            'max_reservation_minutes' => 'nullable|integer|min:30',
            'requires_approval' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:available,unavailable',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da área comum.',
            'closing_time.after' => 'O horário de fechamento deve ser posterior ao de abertura.',
            'max_reservation_minutes.min' => 'A duração máxima deve ser de pelo menos 30 minutos.',
        ];
    }
}
