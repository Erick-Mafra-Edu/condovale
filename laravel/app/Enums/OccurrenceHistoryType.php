<?php

namespace App\Enums;

enum OccurrenceHistoryType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case Comment = 'comment';
    case Completed = 'completed';
}
