<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:100|unique:units,code',
            'number' => 'required|string|max:50',
            'block' => 'nullable|string|max:50',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Informe o código da unidade.',
            'code.unique' => 'Já existe uma unidade com este código.',
            'number.required' => 'Informe o número da unidade.',
            'status.in' => 'A situação informada é inválida.',
        ];
    }
}
