<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
use App\Models\User;

/**
 * RN06 — every change of an occurrence records who did it and the transition.
 */
class CreateOccurrenceHistoryAction
{
    public static function execute(
        Occurrence $occurrence,
        User $author,
        OccurrenceHistoryType $type,
        ?OccurrenceStatus $previousStatus = null,
        ?OccurrenceStatus $newStatus = null,
        ?string $message = null,
    ): OccurrenceHistory {
        return $occurrence->histories()->create([
            'user_id' => $author->id,
            'type' => $type,
            'message' => $message,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
        ]);
    }
}
