<?php

namespace App\Http\Requests;

use App\Http\Utils\SanitizeUtil;
use Illuminate\Foundation\Http\FormRequest;

class OccurrenceTypeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeId = SanitizeUtil::sanitizeInt($this->route('id'));

        return [
            'name' => "sometimes|string|max:60|unique:occurrence_types,name,{$typeId}",
            'description' => 'sometimes|nullable|string|max:255',
            // Reativar é este campo com `true`: um tipo em uso não pode ser
            // apagado, então inativar é a única forma de tirá-lo da lista.
            'active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe um tipo de ocorrência com este nome.',
            'name.max' => 'O nome do tipo deve ter no máximo 60 caracteres.',
            'description.max' => 'A descrição deve ter no máximo 255 caracteres.',
            'active.boolean' => 'A situação deve ser verdadeira ou falsa.',
        ];
    }
}
