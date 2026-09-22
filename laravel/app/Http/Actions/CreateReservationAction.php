<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Creates a reservation for a common area.
 *
 * RN01 — refuses any overlap with a reservation that still blocks the agenda.
 * RN02 — enforces availability, opening hours and the maximum duration set for
 *        the area, so the rule does not depend on the browser.
 */
class CreateReservationAction
{
    /**
     * @param  array{date: string, start_time: ?string, end_time: ?string}  $attributes
     */
    public static function execute(User $resident, CommonArea $commonArea, array $attributes): Reservation
    {
        $date = $attributes['date'];
        $startTime = $attributes['start_time'] ?? null;
        $endTime = $attributes['end_time'] ?? null;

        self::checkArea($commonArea);
        self::checkDate($date);
        self::checkPeriod($commonArea, $startTime, $endTime);
        CheckReservationConflictAction::execute($commonArea, $date, $startTime, $endTime);

        return Reservation::create([
            'common_area_id' => $commonArea->id,
            'resident_id' => $resident->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $commonArea->requires_approval ? ReservationStatus::Pending : ReservationStatus::Approved,
        ]);
    }

    private static function checkArea(CommonArea $commonArea): void
    {
        if (! $commonArea->isAvailable()) {
            throw BusinessRuleException::unprocessable('Esta área comum não está disponível para reserva.');
        }
    }

    private static function checkDate(string $date): void
    {
        if ($date < Carbon::today()->format('Y-m-d')) {
            throw BusinessRuleException::unprocessable('Não é possível reservar uma data passada.');
        }
    }

    /**
     * A reservation without both times is a full day booking; informing only
     * one of them is invalid.
     */
    private static function checkPeriod(CommonArea $commonArea, ?string $startTime, ?string $endTime): void
    {
        $isFullDay = $startTime === null && $endTime === null;

        if (! $isFullDay && ($startTime === null || $endTime === null)) {
            throw BusinessRuleException::unprocessable(
                'Informe os dois horários ou deixe ambos vazios para reservar o dia inteiro.'
            );
        }

        if ($isFullDay) {
            return;
        }

        if ($startTime >= $endTime) {
            throw BusinessRuleException::unprocessable('O horário final deve ser maior que o inicial.');
        }

        if ($commonArea->opening_time && $startTime < $commonArea->opening_time) {
            throw BusinessRuleException::unprocessable("A área abre às {$commonArea->opening_time}.");
        }

        if ($commonArea->closing_time && $endTime > $commonArea->closing_time) {
            throw BusinessRuleException::unprocessable("A área fecha às {$commonArea->closing_time}.");
        }

        $limit = $commonArea->max_reservation_minutes;

        if ($limit && self::minutes($endTime) - self::minutes($startTime) > $limit) {
            throw BusinessRuleException::unprocessable("A duração máxima desta área é de {$limit} minutos.");
        }
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }
}
