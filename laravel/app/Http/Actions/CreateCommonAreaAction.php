<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Enums\CommonAreaStatus;
use App\Enums\TypeLogEnum;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;

/**
 * Registers a common area. A new area accepts reservations unless the form
 * says otherwise, and does not require approval unless asked to.
 */
class CreateCommonAreaAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function execute(array $attributes): CommonArea
    {
        $commonArea = CommonArea::create(self::normalize($attributes) + [
            'status' => CommonAreaStatus::Available->value,
            'requires_approval' => false,
        ]);

        CreateLogAction::execute(
            TypeLogEnum::COMMON_AREA->value,
            "Área comum {$commonArea->id} {$commonArea->name} criada.",
            $commonArea,
            action: AuditAction::CommonAreaCreated,
            subjectId: $commonArea->id,
        );

        return $commonArea->refresh();
    }

    /**
     * The `capacity` column does not accept null: zero is how the table says
     * "not informed", so an empty capacity in the form becomes zero.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalize(array $attributes): array
    {
        if (array_key_exists('capacity', $attributes) && $attributes['capacity'] === null) {
            $attributes['capacity'] = 0;
        }

        // Midnight is kept as "24:00": stored as "00:00", it would sort and
        // compare as the start of the day, before the opening.
        if (array_key_exists('closing_time', $attributes)) {
            $attributes['closing_time'] = TimeUtil::formatEnd($attributes['closing_time']);
        }

        return $attributes;
    }
}
