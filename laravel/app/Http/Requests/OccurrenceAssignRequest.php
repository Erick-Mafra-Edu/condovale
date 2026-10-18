<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Encaminhamento do chamado: para quem e, quando for repasse, por quê.
 *
 * A justificativa é opcional aqui porque só a Action sabe se é obrigatória:
 * depende de o chamado já ter responsável, o que é estado do registro e não
 * formato da requisição.
 */
class OccurrenceAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:users,id',
            'message' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Selecione o funcionário responsável.',
            'employee_id.exists' => 'O funcionário informado não existe.',
            'message.max' => 'A justificativa deve ter no máximo 255 caracteres.',
        ];
    }
}
