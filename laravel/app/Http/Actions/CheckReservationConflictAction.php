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
 *
 * Must run inside the transaction that writes the reservation. Two requests
 * for the same period arriving together would both read "free" and both
 * insert; so the check first locks the area row, which makes the second
 * request wait until the first commits, and then reads the reservations with
 * a locking read, which in InnoDB always sees the latest committed rows — a
 * plain read could still answer from the snapshot the transaction started with.
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
        CommonArea::whereKey($commonArea->id)->lockForUpdate()->first();

        // `date` compared as is, not through whereDate(): the column keeps
        // only the day, and the function would keep MySQL from using the
        // (common_area_id, date, status) index — and lock every row it reads.
        $reservations = Reservation::query()
            ->where('common_area_id', $commonArea->id)
            ->where('date', $date)
            ->blocking()
            ->when($ignoreReservationId, fn ($query, $id) => $query->whereKeyNot($id))
            ->lockForUpdate()
            ->get(['id', 'start_time', 'end_time']);

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
