<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a reservation for a common area.
 *
 * RN02 — CheckReservationRulesAction: availability, allowed hours and the
 *        rules of use of the area.
 * RN01 — CheckReservationConflictAction: no overlap with a reservation that
 *        still blocks the agenda.
 *
 * The same checks ListAvailabilityAction uses to draw the calendar: whatever
 * it shows as free is accepted here, and nothing else.
 */
class CreateReservationAction
{
    /**
     * @param  array{date: string, start_time: ?string, end_time: ?string}  $attributes
     */
    public static function execute(User $resident, CommonArea $commonArea, array $attributes): Reservation
    {
        $date = $attributes['date'];
        $startTime = TimeUtil::format($attributes['start_time'] ?? null);
        $endTime = TimeUtil::formatEnd($attributes['end_time'] ?? null);

        CheckReservationRulesAction::execute($commonArea, $date, $startTime, $endTime);

        return DB::transaction(function () use ($resident, $commonArea, $date, $startTime, $endTime) {
            // Inside the transaction: the check locks the area until the insert
            // commits, so a simultaneous request waits and then sees this one.
            CheckReservationConflictAction::execute($commonArea, $date, $startTime, $endTime);

            $reservation = Reservation::create([
                'common_area_id' => $commonArea->id,
                'resident_id' => $resident->id,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $commonArea->requires_approval ? ReservationStatus::Pending : ReservationStatus::Approved,
            ]);

            CreateLogAction::execute(
                TypeLogEnum::RESERVATION->value,
                self::describe($reservation, $commonArea),
                $reservation,
                action: AuditAction::ReservationCreated,
                subjectId: $reservation->id,
            );

            return $reservation;
        }, Reservation::TRANSACTION_ATTEMPTS);
    }

    /**
     * "Reserva 12 da área 3 Quadra para 24/11/2026, das 10:00 às 12:00, aprovada."
     *
     * Without an outcome, it is the one of a new reservation: "solicitada"
     * when it waits for approval, "aprovada" otherwise.
     */
    public static function describe(Reservation $reservation, CommonArea $commonArea, ?string $outcome = null): string
    {
        $date = $reservation->date->format('d/m/Y');
        $period = $reservation->isFullDay()
            ? 'dia inteiro'
            : 'das '.TimeUtil::format($reservation->start_time).' às '.TimeUtil::formatEnd($reservation->end_time);
        $outcome ??= $reservation->status === ReservationStatus::Pending ? 'solicitada' : 'aprovada';

        return "Reserva {$reservation->id} da área {$commonArea->id} {$commonArea->name} para {$date}, {$period}, {$outcome}.";
    }
}
