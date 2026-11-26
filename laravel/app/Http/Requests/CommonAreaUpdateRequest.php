<?php

namespace App\Http\Requests;

use App\Http\Utils\SanitizeUtil;
use App\Http\Utils\TimeUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial edit of a common area: only what is sent changes.
 *
 * The hours can be changed but not removed. Their order is checked by
 * UpdateCommonAreaAction, which knows the stored value when only one of them
 * is sent.
 */
class CommonAreaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = SanitizeUtil::sanitizeInt($this->route('id'));

        return [
            'name' => "sometimes|required|string|max:120|unique:common_areas,name,{$id}",
            'description' => 'sometimes|nullable|string|max:255',
            'image_url' => 'sometimes|nullable|url:http,https|max:255',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'opening_time' => 'sometimes|required|date_format:H:i',
            'closing_time' => 'sometimes|required|'.TimeUtil::END_TIME_RULE,
            'max_reservation_minutes' => 'sometimes|nullable|integer|min:30|multiple_of:30',
            'requires_approval' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:available,unavailable',
        ];
    }

    public function messages(): array
    {
        return CommonAreaStoreRequest::mensagens();
    }
}
