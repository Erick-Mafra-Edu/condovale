<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\User;

/**
 * RF04/RN05 — the occurrence is always tied to the resident who opened it and
 * starts with a status. The resident and the unit come from the session, never
 * from the request body.
 */
class CreateOccurrenceAction
{
    /**
     * @param  array{title: string, description: string, category: string}  $attributes
     */
    public static function execute(User $resident, array $attributes): Occurrence
    {
        $occurrence = Occurrence::create([
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'category' => $attributes['category'],
            'resident_id' => $resident->id,
            'unit_id' => $resident->unit_id,
            'status' => OccurrenceStatus::Open,
        ]);

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $resident,
            OccurrenceHistoryType::Created,
            null,
            OccurrenceStatus::Open,
        );

        return $occurrence;
    }
}
