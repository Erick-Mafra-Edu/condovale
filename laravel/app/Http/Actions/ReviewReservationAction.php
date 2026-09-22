<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Reservation;

/**
 * UC16 — the administration approves or rejects a pending request.
 *
 * Approving re-checks the conflict because another reservation may have taken
 * the period while this one waited.
 */
class ReviewReservationAction
{
    public static function execute(Reservation $reservation, ReservationStatus $status, ?string $rejectionReason = null): Reservation
    {
        if (! in_array($status, [ReservationStatus::Approved, ReservationStatus::Rejected], true)) {
            throw BusinessRuleException::unprocessable('A análise permite apenas aprovar ou reprovar a solicitação.');
        }

        if ($reservation->status !== ReservationStatus::Pending) {
            throw BusinessRuleException::conflict('Somente solicitações pendentes podem ser analisadas.');
        }

        if ($status === ReservationStatus::Approved) {
            CheckReservationConflictAction::execute(
                $reservation->commonArea,
                $reservation->date->format('Y-m-d'),
                $reservation->start_time,
                $reservation->end_time,
                $reservation->id,
            );
        }

        $reservation->update([
            'status' => $status,
            'rejection_reason' => $status === ReservationStatus::Rejected ? $rejectionReason : null,
        ]);

        return $reservation->refresh();
    }
}
