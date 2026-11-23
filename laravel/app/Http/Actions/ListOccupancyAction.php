<?php

namespace App\Http\Actions;

use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use Illuminate\Support\Collection;

/**
 * RN14 — occupancy of a common area without identifying who booked it.
 *
 * Only the date and the taken period leave the application: no reservation id
 * and no resident data.
 */
class ListOccupancyAction
{
    public static function execute(CommonArea $commonArea, string $startDate, string $endDate): Collection
    {
        return $commonArea->reservations()
            ->blocking()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get(['date', 'start_time', 'end_time'])
            // "HH:MM" whatever the database returns, so MySQL and SQLite give
            // the front-end the same contract.
            ->map(fn ($reservation) => [
                'date' => $reservation->date->format('Y-m-d'),
                'start_time' => TimeUtil::format($reservation->start_time),
                'end_time' => TimeUtil::format($reservation->end_time),
            ])
            ->values();
    }
}
