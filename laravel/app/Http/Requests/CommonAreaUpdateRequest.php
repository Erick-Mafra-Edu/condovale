<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommonAreaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:120',
            'description' => 'sometimes|nullable|string|max:255',
            'image_url' => 'sometimes|nullable|string|max:255',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'opening_time' => 'sometimes|nullable|date_format:H:i',
            'closing_time' => 'sometimes|nullable|date_format:H:i',
            'max_reservation_minutes' => 'sometimes|nullable|integer|min:30',
            'requires_approval' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:available,unavailable',
        ];
    }
}
