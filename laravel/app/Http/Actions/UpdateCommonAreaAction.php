<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use Illuminate\Support\Carbon;

/**
 * Edits a common area, including its situation: `status: unavailable` takes it
 * out of the calendar and `status: available` puts it back.
 *
 * Changing the rules does not touch the reservations already made — they were
 * valid when requested, and cancelling or rejecting them is a decision of the
 * administration, which must be told about it. So the edit goes through and
 * the response says how many future reservations no longer fit.
 */
class UpdateCommonAreaAction
{
    /** Attributes whose change can leave a future reservation out of the rules. */
    private const RULES = ['status', 'opening_time', 'closing_time', 'max_reservation_minutes'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function execute(CommonArea $commonArea, array $attributes): CommonArea
    {
        $attributes = CreateCommonAreaAction::normalize($attributes);

        self::checkHours($commonArea, $attributes);

        $commonArea->update($attributes);

        CreateLogAction::execute(
            TypeLogEnum::COMMON_AREA->value,
            "Área comum {$commonArea->id} {$commonArea->name} editada.",
            (object) $attributes
        );

        return $commonArea->refresh();
    }

    /**
     * "Área comum 3 Quadra editada." plus, when the rules changed and some
     * future reservation no longer fits them, a warning with how many.
     *
     * @param  array<string, mixed>  $attributes  what the request changed
     */
    public static function describe(CommonArea $commonArea, array $attributes): string
    {
        $description = "Área comum {$commonArea->id} {$commonArea->name} editada.";

        if (array_intersect(self::RULES, array_keys($attributes)) === []) {
            return $description;
        }

        $atOdds = self::reservationsAtOdds($commonArea);

        if ($atOdds === 0) {
            return $description;
        }

        $plural = $atOdds === 1
            ? 'reserva futura pendente ou aprovada não se encaixa'
            : 'reservas futuras pendentes ou aprovadas não se encaixam';

        return "{$description} {$atOdds} {$plural} mais nas regras da área: reveja-as na agenda de reservas.";
    }

    /**
     * Future pending or approved reservations the area, as it is now, would
     * refuse: all of them when it is unavailable, or the ones outside the
     * hours, the 30 minute grid or the maximum duration.
     */
    public static function reservationsAtOdds(CommonArea $commonArea): int
    {
        $future = $commonArea->reservations()
            ->blocking()
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->get(['start_time', 'end_time']);

        if (! $commonArea->isAvailable() || ! $commonArea->hasOpeningHours()) {
            return $future->count();
        }

        return $future->filter(function ($reservation) use ($commonArea) {
            try {
                CreateReservationAction::checkPeriod(
                    $commonArea,
                    TimeUtil::format($reservation->start_time),
                    TimeUtil::format($reservation->end_time)
                );

                return false;
            } catch (BusinessRuleException) {
                return true;
            }
        })->count();
    }

    /**
     * Closing must come after opening. A request may send only one of them,
     * so the other one is the stored value.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function checkHours(CommonArea $commonArea, array $attributes): void
    {
        $opening = TimeUtil::toMinutes($attributes['opening_time'] ?? $commonArea->opening_time);
        $closing = TimeUtil::toMinutes($attributes['closing_time'] ?? $commonArea->closing_time);

        if ($opening !== null && $closing !== null && $opening >= $closing) {
            throw BusinessRuleException::unprocessable('O horário de fechamento deve ser posterior ao de abertura.');
        }
    }
}
