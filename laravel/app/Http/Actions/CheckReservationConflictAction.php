<?php

namespace App\Http\Actions;

use App\Exceptions\BusinessRuleException;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use App\Models\Reservation;

/**
 * RN01 — refuses a period already taken for the same area and date.
 *
 * Only pending and approved reservations block the agenda, which is what makes
 * RN08 work: cancelling or rejecting frees the period again.
 */
class CheckReservationConflictAction
{
    public static function execute(
        CommonArea $commonArea,
        string $date,
        ?string $startTime,
        ?string $endTime,
        ?int $ignoreReservationId = null,
    ): void {
        $reservations = $commonArea->reservations()
            ->blocking()
            ->whereDate('date', $date)
            ->when($ignoreReservationId, fn ($query, $id) => $query->whereKeyNot($id))
            ->get();

        foreach ($reservations as $reservation) {
            if (self::overlaps($reservation, $startTime, $endTime)) {
                throw BusinessRuleException::conflict('Já existe uma reserva para esta área, data e horário.');
            }
        }
    }

    private static function overlaps(Reservation $reservation, ?string $startTime, ?string $endTime): bool
    {
        // A full day booking takes the whole date, on either side of the comparison.
        if ($reservation->isFullDay() || $startTime === null || $endTime === null) {
            return true;
        }

        // Compared as minutes: the database may return "12:00:00" for a "12:00"
        // sent by the API, and as strings those two would overlap.
        return TimeUtil::toMinutes($startTime) < TimeUtil::toMinutes($reservation->end_time)
            && TimeUtil::toMinutes($reservation->start_time) < TimeUtil::toMinutes($endTime);
    }
}
