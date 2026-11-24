<?php

namespace App\Http\Controllers;

use App\Http\Actions\CancelReservationAction;
use App\Http\Actions\CreateReservationAction;
use App\Http\Requests\ReservationStoreRequest;
use App\Http\Utils\AuthUtil;
use App\Http\Utils\SanitizeUtil;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Services\MessageService;

/**
 * UC03 / RF03 — reservations of common areas.
 */
class ReservationController extends Controller
{
    /**
     * The resident is always the session user: the payload names the area and
     * the period, never who is booking. RN01 and RN02 are enforced by the
     * Action, which also opens the transaction and writes the audit log.
     */
    public function store(ReservationStoreRequest $request)
    {
        $attributes = $request->validated();
        $commonArea = CommonArea::find($attributes['common_area_id']);

        if (! $commonArea) {
            return MessageService::error("Área comum {$attributes['common_area_id']} não encontrada.", 404);
        }

        try {
            $reservation = CreateReservationAction::execute(AuthUtil::user(), $commonArea, $attributes);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        return MessageService::success(
            CreateReservationAction::describe($reservation, $commonArea),
            $reservation->load('commonArea:id,name')
        );
    }

    /**
     * RN08 — cancels and releases the period at once: a cancelled reservation
     * no longer blocks the agenda. The resident cancels their own; the
     * administration, any. Which is which is the Action's decision.
     */
    public function cancel(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $reservation = Reservation::find($id);

        if (! $reservation) {
            return MessageService::error("Reserva {$id} não encontrada.", 404);
        }

        try {
            $reservation = CancelReservationAction::execute($reservation, AuthUtil::user());
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        $reservation->load('commonArea:id,name');
        $description = CreateReservationAction::describe($reservation, $reservation->commonArea, 'cancelada');

        return MessageService::success("{$description} O período voltou a ficar disponível.", $reservation);
    }
}
