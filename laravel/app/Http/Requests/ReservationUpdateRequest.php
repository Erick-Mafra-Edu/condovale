<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UC16 — approval or rejection of a pending request.
 */
class ReservationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe se a reserva foi aprovada ou reprovada.',
            'status.in' => 'A análise permite apenas aprovar ou reprovar a solicitação.',
            'rejection_reason.string' => 'Informe o motivo da reprovação como texto.',
            'rejection_reason.max' => 'O motivo da reprovação pode ter até 255 caracteres.',
        ];
    }
}
