<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserPermissionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission' => 'required|string|exists:permissions,name',
        ];
    }

    public function messages(): array
    {
        return [
            'permission.required' => 'Informe o caso de uso a conceder.',
            'permission.exists' => 'O caso de uso informado não existe.',
        ];
    }
}
