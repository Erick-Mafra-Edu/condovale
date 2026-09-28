<?php

namespace App\Http\Requests;

use App\Enums\OccupantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitOccupancyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'unit_id' => 'required|integer|exists:units,id',
            'occupant_type' => ['sometimes', Rule::enum(OccupantType::class)],
            'started_at' => 'sometimes|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Informe o morador.',
            'user_id.exists' => 'O morador informado não existe.',
            'unit_id.required' => 'Informe a unidade.',
            'unit_id.exists' => 'A unidade informada não existe.',
            'occupant_type.enum' => 'O tipo de ocupação informado é inválido.',
            'started_at.date_format' => 'A data de início deve estar no formato AAAA-MM-DD.',
        ];
    }
}
