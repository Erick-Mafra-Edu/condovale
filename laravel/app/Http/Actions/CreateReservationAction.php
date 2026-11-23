<?php

namespace App\Http\Actions;

use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
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
 * RN02 — enforces availability, opening hours, the 30 minute grid and the
 *        maximum duration set for the area, so the rule does not depend on the
 *        browser.
 *
 * The checks are the same ListAvailabilityAction uses to draw the calendar:
 * whatever it shows as free is accepted here, and nothing else.
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
        $endTime = TimeUtil::format($attributes['end_time'] ?? null);

        self::checkArea($commonArea);
        self::checkDate($date);
        self::checkPeriod($commonArea, $startTime, $endTime);
        self::checkStartNotPast($date, $startTime);

        return DB::transaction(function () use ($resident, $commonArea, $date, $startTime, $endTime) {
            // Two residents asking for the same period at the same time: the
            // lock on the area makes the second one wait and then see the first.
            CommonArea::where('id', $commonArea->id)->lockForUpdate()->first();
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
                $reservation
            );

            return $reservation;
        });
    }

    /**
     * "Reserva 12 da área 3 Quadra para 24/11/2026, das 10:00 às 12:00, aprovada."
     */
    public static function describe(Reservation $reservation, CommonArea $commonArea): string
    {
        $date = $reservation->date->format('d/m/Y');
        $period = $reservation->isFullDay()
            ? 'dia inteiro'
            : "das {$reservation->start_time} às {$reservation->end_time}";
        $outcome = $reservation->status === ReservationStatus::Pending ? 'solicitada' : 'aprovada';

        return "Reserva {$reservation->id} da área {$commonArea->id} {$commonArea->name} para {$date}, {$period}, {$outcome}.";
    }

    private static function checkArea(CommonArea $commonArea): void
    {
        if (! $commonArea->isAvailable()) {
            throw BusinessRuleException::unprocessable('Esta área comum não está disponível para reserva.');
        }

        // The calendar shows such an area as unavailable: there is no grid to
        // pick from, and accepting a booking here would contradict it.
        if (! $commonArea->hasOpeningHours()) {
            throw BusinessRuleException::unprocessable(
                'Esta área comum não tem horário de funcionamento cadastrado. Procure a administração.'
            );
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

        if ($start < $opening) {
            throw BusinessRuleException::unprocessable('A área abre às '.TimeUtil::fromMinutes($opening).'.');
        }

        if ($end > $closing) {
            throw BusinessRuleException::unprocessable('A área fecha às '.TimeUtil::fromMinutes($closing).'.');
        }

        // Blocks count from opening time. The last one may be shorter when
        // closing time is off the grid, so ending exactly at closing is fine.
        $offGrid = ($start - $opening) % CommonArea::SLOT_MINUTES !== 0
            || (($end - $opening) % CommonArea::SLOT_MINUTES !== 0 && $end !== $closing);

        if ($offGrid) {
            throw BusinessRuleException::unprocessable(sprintf(
                'Escolha horários em blocos de %d minutos a partir da abertura, às %s.',
                CommonArea::SLOT_MINUTES,
                TimeUtil::fromMinutes($opening)
            ));
        }

        $limit = $commonArea->maxReservationMinutes();

        if ($end - $start > $limit) {
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
