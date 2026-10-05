<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Informe o perfil.',
            'role.enum' => 'O perfil informado é inválido.',
        ];
    }
}
