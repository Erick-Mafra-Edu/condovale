<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
use App\Enums\UseCase;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RN08 — "o cancelamento de uma reserva deverá liberar novamente o respectivo
 * período para novas reservas".
 *
 * The release is the status itself: only pending and approved reservations
 * block the agenda (ReservationStatus::blocking()), so a cancelled one stops
 * counting for the conflict check and for the availability at once.
 *
 * Ownership is checked here, not in the controller: the route group only knows
 * that the user may cancel reservations, not which ones are theirs. The
 * resident cancels their own; the administration (manage-reservations) any.
 */
class CancelReservationAction
{
    public static function execute(Reservation $reservation, User $user): Reservation
    {
        $isAdministration = $user->hasUseCase(UseCase::ManageReservations);

        if (! $isAdministration && $reservation->resident_id !== $user->id) {
            throw BusinessRuleException::forbidden('Você só pode cancelar as suas próprias reservas.');
        }

        return DB::transaction(function () use ($reservation, $user, $isAdministration) {
            // Area first, then the reservation — the order creation and
            // approval follow. Locking only the reservation left this path
            // and the approval taking the same locks in opposite orders, and
            // MySQL answered with deadlocks.
            CommonArea::whereKey($reservation->common_area_id)->lockForUpdate()->first();

            // The status is read again under lock: an approval running at the
            // same moment would otherwise write "approved" over this
            // cancellation, and the period would never be released.
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            self::checkCanCancel($reservation);

            $reservation->update(['status' => ReservationStatus::Cancelled]);

            $byAdministration = $isAdministration && $reservation->resident_id !== $user->id;
            $commonArea = $reservation->commonArea;

            CreateLogAction::execute(
                TypeLogEnum::RESERVATION->value,
                CreateReservationAction::describe($reservation, $commonArea, $byAdministration ? 'cancelada pela administração' : 'cancelada'),
                ['id' => $reservation->id, 'common_area_id' => $commonArea->id, 'resident_id' => $reservation->resident_id]
            );

            return $reservation->refresh();
        }, Reservation::TRANSACTION_ATTEMPTS);
    }

    private static function checkCanCancel(Reservation $reservation): void
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            throw BusinessRuleException::conflict('Esta reserva já está cancelada.');
        }

        if ($reservation->status === ReservationStatus::Rejected) {
            throw BusinessRuleException::conflict('Uma reserva reprovada não pode ser cancelada.');
        }

        // What already happened stays in the history the reports read. A
        // reservation in progress can still be cancelled, as the reservation
        // screen allows: the rest of the period is released.
        if (self::hasEnded($reservation)) {
            throw BusinessRuleException::conflict('Esta reserva já terminou e não pode mais ser cancelada.');
        }
    }

    /**
     * The same "finished" the reservation screen uses: a past date, or today
     * with the end time already reached. A full day booking ends with the day.
     */
    private static function hasEnded(Reservation $reservation): bool
    {
        $date = $reservation->date->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        if ($date !== $today) {
            return $date < $today;
        }

        if ($reservation->end_time === null) {
            return false;
        }

        $now = Carbon::now();

        return TimeUtil::toMinutes($reservation->end_time) <= $now->hour * 60 + $now->minute;
    }
}
