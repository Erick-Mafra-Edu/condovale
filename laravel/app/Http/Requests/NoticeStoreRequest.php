<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF05 — official notice. The author is the session user, never the payload.
 */
class NoticeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:120',
            'content' => 'required|string|max:5000',
            'status' => 'sometimes|string|in:draft,published',
            'published_at' => 'sometimes|nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o título do comunicado.',
            'content.required' => 'Informe o conteúdo do comunicado.',
            'status.in' => 'Situação inválida. Use rascunho ou publicado.',
        ];
    }
}
