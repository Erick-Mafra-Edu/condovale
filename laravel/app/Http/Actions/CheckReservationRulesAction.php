<?php

namespace App\Http\Actions;

use App\Exceptions\BusinessRuleException;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use Illuminate\Support\Carbon;

/**
 * RN02 — "toda reserva deverá respeitar a disponibilidade, os horários
 * permitidos e as regras de utilização definidas para a área comum".
 *
 * What the area defines, checked against a requested period:
 *  - availability: the area accepts reservations and has opening hours;
 *  - allowed hours: not in the past, between opening and closing, on the
 *    30 minute grid counted from opening (RN14);
 *  - rules of use: up to the area's maximum duration, two hours when it sets
 *    none (RN14). A full day booking is the whole day and has no duration.
 *
 * The sibling of CheckReservationConflictAction (RN01). Both run on creation
 * and again on approval, because the area may have changed while the request
 * waited. ListAvailabilityAction draws the calendar with the same rules.
 *
 * Every refusal is a 422 that names the field, so the response carries
 * `errors.<field>` like a failed validation.
 */
class CheckReservationRulesAction
{
    public static function execute(CommonArea $commonArea, string $date, ?string $startTime, ?string $endTime): void
    {
        self::checkArea($commonArea);
        self::checkDate($date);
        self::checkPeriod($commonArea, $startTime, $endTime);
        self::checkStartNotPast($date, $startTime);
    }

    private static function checkArea(CommonArea $commonArea): void
    {
        if (! $commonArea->isAvailable()) {
            throw BusinessRuleException::unprocessable('Esta área comum não está disponível para reserva.', 'common_area_id');
        }

        // The calendar shows such an area as unavailable: there is no grid to
        // pick from, and accepting a booking here would contradict it.
        if (! $commonArea->hasOpeningHours()) {
            throw BusinessRuleException::unprocessable(
                'Esta área comum não tem horário de funcionamento cadastrado. Procure a administração.',
                'common_area_id'
            );
        }
    }

    private static function checkDate(string $date): void
    {
        if ($date < Carbon::today()->format('Y-m-d')) {
            throw BusinessRuleException::unprocessable('Não é possível reservar uma data passada.', 'date');
        }
    }

    /**
     * The period against the hours, the grid and the duration of the area.
     *
     * Public because UpdateCommonAreaAction runs the future reservations
     * through it after the area rules change: the rule that decides what may
     * be booked is the same that decides what no longer fits.
     */
    public static function checkPeriod(CommonArea $commonArea, ?string $startTime, ?string $endTime): void
    {
        $isFullDay = $startTime === null && $endTime === null;

        if (! $isFullDay && ($startTime === null || $endTime === null)) {
            throw BusinessRuleException::unprocessable(
                'Informe os dois horários ou deixe ambos vazios para reservar o dia inteiro.',
                $startTime === null ? 'start_time' : 'end_time'
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
            throw BusinessRuleException::unprocessable('O horário final deve ser maior que o inicial.', 'end_time');
        }

        if ($start < $opening) {
            throw BusinessRuleException::unprocessable('A área abre às '.TimeUtil::fromMinutes($opening).'.', 'start_time');
        }

        if ($end > $closing) {
            throw BusinessRuleException::unprocessable('A área fecha às '.TimeUtil::fromMinutes($closing).'.', 'end_time');
        }

        // Blocks count from opening time. The last one may be shorter when
        // closing time is off the grid, so ending exactly at closing is fine.
        $startOffGrid = ($start - $opening) % CommonArea::SLOT_MINUTES !== 0;
        $endOffGrid = ($end - $opening) % CommonArea::SLOT_MINUTES !== 0 && $end !== $closing;

        if ($startOffGrid || $endOffGrid) {
            throw BusinessRuleException::unprocessable(sprintf(
                'Escolha horários em blocos de %d minutos a partir da abertura, às %s.',
                CommonArea::SLOT_MINUTES,
                TimeUtil::fromMinutes($opening)
            ), $startOffGrid ? 'start_time' : 'end_time');
        }

        $limit = $commonArea->maxReservationMinutes();

        if ($end - $start > $limit) {
            throw BusinessRuleException::unprocessable("A duração máxima desta área é de {$limit} minutos.", 'end_time');
        }
    }

    /**
     * On the current day, a period that already started is as past as
     * yesterday: the availability query shows it as taken by time.
     */
    private static function checkStartNotPast(string $date, ?string $startTime): void
    {
        if ($startTime === null || $date !== Carbon::today()->format('Y-m-d')) {
            return;
        }

        $now = Carbon::now();

        if (TimeUtil::toMinutes($startTime) < $now->hour * 60 + $now->minute) {
            throw BusinessRuleException::unprocessable('Não é possível reservar um horário que já passou.', 'start_time');
        }
    }
}
