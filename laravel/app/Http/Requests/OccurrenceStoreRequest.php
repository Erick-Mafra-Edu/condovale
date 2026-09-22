<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF04/RN05 — the occurrence carries a textual description and is tied to the
 * resident of the session.
 */
class OccurrenceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:2000',
            'category' => 'required|string|max:60',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o título da ocorrência.',
            'description.required' => 'Descreva a ocorrência.',
            'category.required' => 'Informe o tipo da ocorrência.',
        ];
    }
}
