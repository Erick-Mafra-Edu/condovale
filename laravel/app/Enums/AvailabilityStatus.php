<?php

namespace App\Enums;

/**
 * RN14 — the four states a day of a common area can be in.
 */
enum AvailabilityStatus: string
{
    case Available = 'available';
    case Partial = 'partial';
    case Occupied = 'occupied';
    case Unavailable = 'unavailable';

    /**
     * The same words as the reservation screen, so the API and the calendar
     * never name the same state differently.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponível',
            self::Partial => 'Parcialmente ocupado',
            self::Occupied => 'Ocupado',
            self::Unavailable => 'Indisponível',
        };
    }
}
