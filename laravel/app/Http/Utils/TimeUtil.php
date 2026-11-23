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
}
