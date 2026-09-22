<?php

namespace App\Http\Actions;

use App\Enums\UseCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * RN14 — the resident only reaches their own reservations; the administration
 * reaches the whole agenda. Keeps one resident from reading who booked what.
 */
class ScopeReservationsAction
{
    public static function execute(Builder $query, User $user): Builder
    {
        if ($user->hasUseCase(UseCase::ManageReservations)) {
            return $query;
        }

        return $query->where('resident_id', $user->id);
    }
}
