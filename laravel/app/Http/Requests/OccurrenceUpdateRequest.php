<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RN06 — mudança de status do chamado.
 *
 * Aqui só se valida que o status existe. Qual transição cada pessoa pode
 * fazer depende de quem ela é e de em que ponto o chamado está, e quem decide
 * isso é a UpdateOccurrenceStatusAction — que também explica por que recusou.
 */
class OccurrenceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:open,analysis,assigned,in_progress,completed,cancelled',
            'message' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo status da ocorrência.',
            'status.in' => 'Status de ocorrência inválido.',
            'message.max' => 'A observação deve ter no máximo 255 caracteres.',
        ];
    }
}
