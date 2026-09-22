<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * RN01/RN08 — somente reservas pendentes e aprovadas ocupam a agenda;
     * cancelar ou reprovar libera novamente o período.
     */
    public function blocksSchedule(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }

    /** @return list<string> */
    public static function blocking(): array
    {
        return [self::Pending->value, self::Approved->value];
    }
}
