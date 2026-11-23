<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        self::checkStartNotPast($date, $startTime);

        return DB::transaction(function () use ($resident, $commonArea, $date, $startTime, $endTime) {
            CommonArea::where('id', $commonArea->id)->lockForUpdate()->first();
            CheckReservationConflictAction::execute($commonArea, $date, $startTime, $endTime);

            return Reservation::create([
                'common_area_id' => $commonArea->id,
                'resident_id' => $resident->id,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $commonArea->requires_approval ? ReservationStatus::Pending : ReservationStatus::Approved,
            ]);
        });
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

        $start = TimeUtil::toMinutes($startTime);
        $end = TimeUtil::toMinutes($endTime);
        $opening = TimeUtil::toMinutes($commonArea->opening_time);
        $closing = TimeUtil::toMinutes($commonArea->closing_time);

        if ($start >= $end) {
            throw BusinessRuleException::unprocessable('O horário final deve ser maior que o inicial.');
        }

        if ($opening !== null && $start < $opening) {
            throw BusinessRuleException::unprocessable('A área abre às '.TimeUtil::fromMinutes($opening).'.');
        }

        if ($closing !== null && $end > $closing) {
            throw BusinessRuleException::unprocessable('A área fecha às '.TimeUtil::fromMinutes($closing).'.');
        }

        $limit = $commonArea->max_reservation_minutes;

        if ($limit && $end - $start > $limit) {
            throw BusinessRuleException::unprocessable("A duração máxima desta área é de {$limit} minutos.");
        }
    }

    /**
     * On the current day, a period that already started is as past as
     * yesterday: the availability query shows it as taken by time, so the
     * creation refuses it too.
     */
    private static function checkStartNotPast(string $date, ?string $startTime): void
    {
        if ($startTime === null || $date !== Carbon::today()->format('Y-m-d')) {
            return;
        }

        $now = Carbon::now();

        if (TimeUtil::toMinutes($startTime) < $now->hour * 60 + $now->minute) {
            throw BusinessRuleException::unprocessable('Não é possível reservar um horário que já passou.');
        }
    }
}
