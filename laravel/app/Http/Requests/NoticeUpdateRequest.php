<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoticeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Author and dates are not in the rules on purpose: they come from the
        // server, and a field outside the rules never reaches validated().
        return [
            'title' => 'sometimes|required|string|max:120',
            'content' => 'sometimes|required|string|max:5000',
            'status' => 'sometimes|string|in:draft,published',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título do comunicado não pode ficar vazio.',
            'title.max' => 'O título do comunicado deve ter no máximo 120 caracteres.',
            'content.required' => 'O conteúdo do comunicado não pode ficar vazio.',
            'content.max' => 'O conteúdo do comunicado deve ter no máximo 5000 caracteres.',
            'status.in' => 'Situação inválida. Use rascunho ou publicado.',
        ];
    }
}
