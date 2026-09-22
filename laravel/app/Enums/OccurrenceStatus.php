<?php

namespace App\Enums;

enum OccurrenceStatus: string
{
    case Open = 'open';
    case Analysis = 'analysis';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
