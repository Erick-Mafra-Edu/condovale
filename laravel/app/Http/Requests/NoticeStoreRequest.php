<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF05 — official notice. The author is the session user, never the payload.
 *
 * Nem autor nem data de publicação são aceitos do corpo: os dois são do
 * servidor (ver CreateNoticeAction). O frontend ainda manda `authorId` e
 * `publishedAt`, e eles são ignorados.
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
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o título do comunicado.',
            'title.max' => 'O título do comunicado deve ter no máximo 120 caracteres.',
            'content.required' => 'Informe o conteúdo do comunicado.',
            'content.max' => 'O conteúdo do comunicado deve ter no máximo 5000 caracteres.',
            'status.in' => 'Situação inválida. Use rascunho ou publicado.',
        ];
    }
}
