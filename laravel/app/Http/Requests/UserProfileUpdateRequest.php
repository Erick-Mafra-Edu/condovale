<?php

namespace App\Http\Requests;

use App\Http\Utils\AuthUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RN17 — only name and e-mail. Any other field sent by the client is simply
 * not validated and therefore never reaches the update.
 */
class UserProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = AuthUtil::id();

        return [
            'name' => 'required|string|max:120',
            'email' => "required|email|max:180|unique:users,email,{$userId}",
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o seu nome.',
            'email.required' => 'Informe o seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
        ];
    }
}
