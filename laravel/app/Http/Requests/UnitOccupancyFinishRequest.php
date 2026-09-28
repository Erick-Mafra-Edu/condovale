<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitOccupancyFinishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ended_at' => 'sometimes|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'ended_at.date_format' => 'A data de encerramento deve estar no formato AAAA-MM-DD.',
        ];
    }
}
