<?php

namespace App\Http\Actions;

use App\Enums\AvailabilityStatus;
use App\Http\Utils\TimeUtil;
use App\Models\CommonArea;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * RF03 / RN01 / RN02 / RN14 — the free periods of a common area, day by day.
 *
 * It applies the same rules the reservation creation enforces, so whatever is
 * shown as free can actually be booked:
 *  - RN01: pending and approved reservations take their period, and a full day
 *    booking takes the whole date;
 *  - RN02: the area must be available, and the grid runs from opening to
 *    closing time in 30 minute blocks;
 *  - past dates, and the blocks of today that already started, are not bookable.
 *
 * It is built on the anonymised occupancy (ListOccupancyAction), so nothing
 * that identifies who booked leaves the application.
 */
class ListAvailabilityAction
{
    private const SLOT_AVAILABLE = 'available';

    private const SLOT_OCCUPIED = 'occupied';

    private const SLOT_PAST = 'past';

    /** Why a day is unavailable, for the calendar to explain it. */
    private const REASONS = [
        'area_unavailable' => 'Área indisponível para reserva.',
        'past_date' => 'Data passada.',
        'no_opening_hours' => 'A área não tem horário de funcionamento cadastrado.',
        'closed_for_today' => 'O horário de funcionamento de hoje já terminou.',
    ];

    public static function execute(CommonArea $commonArea, string $startDate, string $endDate): array
    {
        $occupancy = ListOccupancyAction::execute($commonArea, $startDate, $endDate)->groupBy('date');
        $grid = self::grid($commonArea);

        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now();
        $nowMinutes = $now->hour * 60 + $now->minute;

        $days = [];

        foreach (CarbonPeriod::create($startDate, $endDate) as $day) {
            $date = $day->format('Y-m-d');

            $days[] = self::day(
                $commonArea,
                $date,
                $occupancy->get($date, collect()),
                $grid,
                $date < $today,
                $date === $today ? $nowMinutes : null,
            );
        }

        return [
            'common_area' => [
                'id' => $commonArea->id,
                'name' => $commonArea->name,
                'status' => $commonArea->status?->value,
                'opening_time' => TimeUtil::format($commonArea->opening_time),
                'closing_time' => TimeUtil::format($commonArea->closing_time),
                'max_reservation_minutes' => $commonArea->maxReservationMinutes(),
                'requires_approval' => $commonArea->requires_approval,
            ],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'slot_minutes' => CommonArea::SLOT_MINUTES,
            'days' => $days,
        ];
    }

    /**
     * The 30 minute blocks between opening and closing, as minutes since
     * midnight. The last block is shorter when closing time is not aligned.
     *
     * @return list<array{0: int, 1: int}>
     */
    private static function grid(CommonArea $commonArea): array
    {
        $opening = TimeUtil::toMinutes($commonArea->opening_time);
        $closing = TimeUtil::toMinutes($commonArea->closing_time);

        if ($opening === null || $closing === null || $opening >= $closing) {
            return [];
        }

        $grid = [];

        for ($from = $opening; $from < $closing; $from += CommonArea::SLOT_MINUTES) {
            $grid[] = [$from, min($from + CommonArea::SLOT_MINUTES, $closing)];
        }

        return $grid;
    }

    /**
     * @param  Collection<int, array{date: string, start_time: ?string, end_time: ?string}>  $taken
     * @param  list<array{0: int, 1: int}>  $grid
     * @param  ?int  $nowMinutes  current time, only when the date is today
     */
    private static function day(
        CommonArea $commonArea,
        string $date,
        Collection $taken,
        array $grid,
        bool $isPastDate,
        ?int $nowMinutes,
    ): array {
        $reason = match (true) {
            ! $commonArea->isAvailable() => 'area_unavailable',
            $isPastDate => 'past_date',
            $grid === [] => 'no_opening_hours',
            default => null,
        };

        $slots = $reason === null ? self::slots($grid, $taken, $nowMinutes) : [];
        $bookable = array_values(array_filter($slots, fn ($slot) => $slot['status'] !== self::SLOT_PAST));

        if ($reason === null && $bookable === []) {
            $reason = 'closed_for_today';
            $slots = [];
        }

        $free = count(array_filter($bookable, fn ($slot) => $slot['status'] === self::SLOT_AVAILABLE));

        // Only what can still be booked counts: on today, the blocks that
        // already started neither free nor take the day.
        $status = match (true) {
            $reason !== null => AvailabilityStatus::Unavailable,
            $free === count($bookable) => AvailabilityStatus::Available,
            $free === 0 => AvailabilityStatus::Occupied,
            default => AvailabilityStatus::Partial,
        };

        return [
            'date' => $date,
            'status' => $status->value,
            'label' => $status->label(),
            'reason' => $reason,
            'reason_label' => $reason === null ? null : self::REASONS[$reason],
            // A full day booking needs the date untouched by any reservation,
            // the same check CheckReservationConflictAction applies.
            'full_day_available' => $reason === null && $taken->isEmpty(),
            'occupied_periods' => $taken
                ->map(fn ($period) => ['start_time' => $period['start_time'], 'end_time' => $period['end_time']])
                ->values()
                ->all(),
            'free_periods' => self::freePeriods($slots),
            'slots' => $slots,
        ];
    }

    /**
     * @param  Collection<int, array{date: string, start_time: ?string, end_time: ?string}>  $taken
     * @param  list<array{0: int, 1: int}>  $grid
     * @return list<array{start_time: string, end_time: string, status: string}>
     */
    private static function slots(array $grid, Collection $taken, ?int $nowMinutes): array
    {
        $fullDay = $taken->contains(fn ($period) => $period['start_time'] === null || $period['end_time'] === null);

        $periods = $taken
            ->reject(fn ($period) => $period['start_time'] === null || $period['end_time'] === null)
            ->map(fn ($period) => [TimeUtil::toMinutes($period['start_time']), TimeUtil::toMinutes($period['end_time'])])
            ->all();

        return array_map(function (array $block) use ($fullDay, $periods, $nowMinutes) {
            [$from, $to] = $block;

            $overlaps = $fullDay;

            foreach ($periods as [$start, $end]) {
                $overlaps = $overlaps || ($start < $to && $from < $end);
            }

            $status = match (true) {
                $nowMinutes !== null && $from < $nowMinutes => self::SLOT_PAST,
                $overlaps => self::SLOT_OCCUPIED,
                default => self::SLOT_AVAILABLE,
            };

            return [
                'start_time' => TimeUtil::fromMinutes($from),
                'end_time' => TimeUtil::fromMinutes($to),
                'status' => $status,
            ];
        }, $grid);
    }

    /**
     * Contiguous free blocks merged into periods, with their length so the
     * client can tell whether the period fits the maximum duration.
     *
     * @param  list<array{start_time: string, end_time: string, status: string}>  $slots
     * @return list<array{start_time: string, end_time: string, minutes: int}>
     */
    private static function freePeriods(array $slots): array
    {
        $periods = [];

        foreach ($slots as $slot) {
            if ($slot['status'] !== self::SLOT_AVAILABLE) {
                continue;
            }

            $last = array_key_last($periods);

            if ($last !== null && $periods[$last]['end_time'] === $slot['start_time']) {
                $periods[$last]['end_time'] = $slot['end_time'];
            } else {
                $periods[] = ['start_time' => $slot['start_time'], 'end_time' => $slot['end_time']];
            }
        }

        return array_map(fn ($period) => [
            ...$period,
            'minutes' => TimeUtil::toMinutes($period['end_time']) - TimeUtil::toMinutes($period['start_time']),
        ], $periods);
    }
}
