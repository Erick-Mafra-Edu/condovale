<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeployOptimizeRequest extends FormRequest
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
            'clear' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'clear.boolean' => 'O campo clear deve ser verdadeiro ou falso.',
        ];
    }
}
