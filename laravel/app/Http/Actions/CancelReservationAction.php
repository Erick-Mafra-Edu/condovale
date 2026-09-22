<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Enums\UseCase;
use App\Exceptions\BusinessRuleException;
use App\Models\Reservation;
use App\Models\User;

/**
 * RN08 — cancelling a reservation frees the period for new bookings.
 *
 * Ownership is checked here, not in the controller: the route group only knows
 * that the user may cancel reservations, not which ones are theirs.
 */
class CancelReservationAction
{
    public static function execute(Reservation $reservation, User $user): Reservation
    {
        if (! $user->hasUseCase(UseCase::ManageReservations) && $reservation->resident_id !== $user->id) {
            throw BusinessRuleException::forbidden('Você só pode cancelar as suas próprias reservas.');
        }

        if ($reservation->status === ReservationStatus::Rejected) {
            throw BusinessRuleException::conflict('Uma reserva reprovada não pode ser cancelada.');
        }

        if ($reservation->status !== ReservationStatus::Cancelled) {
            $reservation->update(['status' => ReservationStatus::Cancelled]);
        }

        return $reservation->refresh();
    }
}
