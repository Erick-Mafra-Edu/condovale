<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => 'sometimes|string|max:20',
            'block' => 'sometimes|nullable|string|max:20',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
