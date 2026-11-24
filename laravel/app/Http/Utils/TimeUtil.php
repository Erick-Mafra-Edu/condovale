<?php

namespace App\Http\Utils;

/**
 * Times of day as minutes since midnight.
 *
 * MySQL returns a TIME column as "HH:MM:SS" while the API receives "HH:MM", and
 * comparing the two as strings is wrong at the boundaries: "12:00" < "12:00:00"
 * is true, which turns two back-to-back reservations into a conflict. Every
 * comparison of times goes through minutes instead.
 */
class TimeUtil
{
    /** Midnight at the end of the day, as minutes: what "24:00" stands for. */
    public const END_OF_DAY = 1440;

    /**
     * Validation rule for a time that ends a period (an area's closing, a
     * reservation's end): "HH:MM" from 00:00 to 23:59, plus "24:00", the
     * midnight at the end of the day. "00:00" is accepted as that same
     * midnight, since nothing can end at the very start of the day.
     *
     * Written without "|", which the string form of the rules would split:
     * the lookaheads refuse hours 25 to 29 and any "24:" that is not "24:00".
     */
    public const END_TIME_RULE = 'regex:/^(?!2[5-9])(?!24:(?!00))[0-2]\d:[0-5]\d$/';

    public static function toMinutes(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }

    /**
     * Formats minutes since midnight as "HH:MM".
     */
    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Normalizes any stored time to "HH:MM", keeping null for full day bookings.
     */
    public static function format(?string $time): ?string
    {
        $minutes = self::toMinutes($time);

        return $minutes === null ? null : self::fromMinutes($minutes);
    }

    /**
     * Like format(), for a time that ends a period: "00:00" becomes "24:00",
     * so the end is never before the start and comparisons in minutes hold.
     */
    public static function formatEnd(?string $time): ?string
    {
        $minutes = self::toMinutes($time);

        if ($minutes === null) {
            return null;
        }

        return self::fromMinutes($minutes === 0 ? self::END_OF_DAY : $minutes);
    }
}
