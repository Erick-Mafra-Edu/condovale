<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = (int) $this->route('id');

        return [
            'name' => 'sometimes|string|max:120',
            'email' => "sometimes|email|max:180|unique:users,email,{$userId}",
            'role' => 'sometimes|string|in:resident,employee,syndic,admin',
            'status' => 'sometimes|string|in:active,inactive',
            'unit_id' => 'sometimes|nullable|integer|exists:units,id',
            'avatar_url' => 'sometimes|nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está em uso.',
            'role.in' => 'Perfil inválido. Use morador, funcionário, síndico ou administrador.',
            'unit_id.exists' => 'A unidade informada não existe.',
        ];
    }
}
