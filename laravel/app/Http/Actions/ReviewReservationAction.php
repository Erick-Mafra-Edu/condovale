<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * UC16 — the administration approves or rejects a pending request.
 *
 * Approving checks the request again, as the area and the agenda are now: the
 * area may have been made unavailable or changed its hours while the request
 * waited (RN02), and another reservation may have taken the period (RN01).
 * Rejecting checks nothing — it is the way out for a request that no longer
 * fits.
 */
class ReviewReservationAction
{
    public static function execute(Reservation $reservation, ReservationStatus $status, ?string $rejectionReason = null): Reservation
    {
        if (! in_array($status, [ReservationStatus::Approved, ReservationStatus::Rejected], true)) {
            throw BusinessRuleException::unprocessable('A análise permite apenas aprovar ou reprovar a solicitação.');
        }

        // The checks and the update share one transaction, so the locks hold
        // until the decision is written.
        return DB::transaction(function () use ($reservation, $status, $rejectionReason) {
            // Area first, then the reservation: the same order the creation
            // follows, so the two never wait on each other in a cycle.
            CommonArea::whereKey($reservation->common_area_id)->lockForUpdate()->first();

            // The status is read again under lock: a cancellation at the same
            // moment must not be overwritten by this decision (RN08).
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== ReservationStatus::Pending) {
                throw BusinessRuleException::conflict('Somente solicitações pendentes podem ser analisadas.');
            }

            if ($status === ReservationStatus::Approved) {
                $commonArea = $reservation->commonArea;
                $date = $reservation->date->format('Y-m-d');
                $startTime = TimeUtil::format($reservation->start_time);
                $endTime = TimeUtil::formatEnd($reservation->end_time);

                self::checkStillAllowed($commonArea, $date, $startTime, $endTime);
                CheckReservationConflictAction::execute($commonArea, $date, $startTime, $endTime, $reservation->id);
            }

            $reservation->update([
                'status' => $status,
                'rejection_reason' => $status === ReservationStatus::Rejected ? $rejectionReason : null,
            ]);

            return $reservation->refresh();
        }, Reservation::TRANSACTION_ATTEMPTS);
    }

    /**
     * The rules speak to whoever books ("Não é possível reservar..."); here
     * the reader is the administration, who needs to know what to do next.
     */
    private static function checkStillAllowed(CommonArea $commonArea, string $date, ?string $startTime, ?string $endTime): void
    {
        try {
            CheckReservationRulesAction::execute($commonArea, $date, $startTime, $endTime);
        } catch (BusinessRuleException $e) {
            throw BusinessRuleException::unprocessable(
                'Esta solicitação não pode mais ser aprovada: '.lcfirst($e->getMessage()).' Reprove-a informando o motivo.',
                $e->field()
            );
        }
    }
}
