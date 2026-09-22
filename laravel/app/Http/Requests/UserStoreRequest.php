<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:180|unique:users,email',
            'role' => 'required|string|in:resident,employee,syndic,admin',
            'status' => 'sometimes|string|in:active,inactive',
            'unit_id' => 'nullable|integer|exists:units,id',
            'password' => 'nullable|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do usuário.',
            'email.required' => 'Informe o e-mail do usuário.',
            'email.unique' => 'Este e-mail já está em uso.',
            'role.required' => 'Informe o perfil do usuário.',
            'role.in' => 'Perfil inválido. Use morador, funcionário, síndico ou administrador.',
            'unit_id.exists' => 'A unidade informada não existe.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
        ];
    }
}
