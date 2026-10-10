<?php

namespace App\Http\Requests;

use App\Http\Utils\SanitizeUtil;
use Illuminate\Foundation\Http\FormRequest;

class UnitUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = SanitizeUtil::sanitizeInt($this->route('id'));

        return [
            // O código pode ser corrigido — renumeração de bloco acontece —,
            // mas continua único: ele é a identidade da unidade nos relatórios
            // e no vínculo com o morador.
            'code' => "sometimes|string|max:100|unique:units,code,{$unitId}",
            'number' => 'sometimes|string|max:50',
            'block' => 'sometimes|nullable|string|max:50',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Já existe uma unidade com este código.',
            'status.in' => 'A situação informada é inválida.',
        ];
    }
}
