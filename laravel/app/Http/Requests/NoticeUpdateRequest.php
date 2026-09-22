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
        return [
            'title' => 'sometimes|string|max:120',
            'content' => 'sometimes|string|max:5000',
            'status' => 'sometimes|string|in:draft,published',
            'published_at' => 'sometimes|nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Situação inválida. Use rascunho ou publicado.',
        ];
    }
}
