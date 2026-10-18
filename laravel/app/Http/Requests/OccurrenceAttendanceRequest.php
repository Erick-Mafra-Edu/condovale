<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Atualização do atendimento: um novo status, uma anotação, ou os dois.
 *
 * `resident_id` não está aqui de propósito. O que não é validado não chega à
 * Action, e o chamado continua sendo de quem o abriu.
 */
class OccurrenceAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:open,analysis,assigned,in_progress,completed,cancelled',
            'message' => 'required_without:status|nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status de ocorrência inválido.',
            'message.required_without' => 'Descreva a atualização do atendimento ou informe o novo status.',
            'message.max' => 'A atualização deve ter no máximo 2000 caracteres.',
        ];
    }
}
