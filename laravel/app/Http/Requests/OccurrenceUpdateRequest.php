<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UC10 — progress of the attendance. The allowed transitions per role are a
 * business rule and live in UpdateOccurrenceStatusAction.
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
        ];
    }
}
