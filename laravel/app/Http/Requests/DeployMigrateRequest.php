<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeployMigrateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // O token também pode vir no cabeçalho X-Deploy-Token; quem confere é
        // o middleware deploy.token, aqui só se garante o formato.
        return [
            'token' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'token.string' => 'O token de implantação deve ser um texto.',
        ];
    }
}
