<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UC8 — forwarding an occurrence to an employee.
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
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Selecione o funcionário responsável.',
            'employee_id.exists' => 'O funcionário informado não existe.',
        ];
    }
}
