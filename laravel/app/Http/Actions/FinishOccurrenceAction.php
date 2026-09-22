<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\User;

/**
 * UC11/RN06 — the assigned employee finishes the attendance, with an optional
 * note that goes to the history.
 */
class FinishOccurrenceAction
{
    public static function execute(Occurrence $occurrence, User $author, ?string $message = null): Occurrence
    {
        UpdateOccurrenceStatusAction::checkAuthor($occurrence, $author);

        if ($occurrence->status !== OccurrenceStatus::InProgress) {
            throw BusinessRuleException::conflict('Somente uma ocorrência em andamento pode ser finalizada.');
        }

        $previousStatus = $occurrence->status;

        $occurrence->update([
            'status' => OccurrenceStatus::Completed,
            'completed_at' => now(),
        ]);

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::Completed,
            $previousStatus,
            OccurrenceStatus::Completed,
            $message,
        );

        return $occurrence->refresh();
    }
}
