<?php

namespace App\Enums;

/**
 * Ids of the type_logs reference table, seeded from
 * database/seeders/json/type_logs.json. The ids are contract: never write the
 * literal number in the code, always use this enum.
 */
enum TypeLogEnum: int
{
    case LOGIN = 1;
    case LOGOUT = 2;
    case USER = 3;
    case UNIT = 4;
    case COMMON_AREA = 5;
    case RESERVATION = 6;
    case OCCURRENCE = 7;
    case NOTICE = 8;
    case UNIT_OCCUPANCY = 9;

    /**
     * Entity name exposed by the audit report (RN16).
     */
    public function entity(): string
    {
        return match ($this) {
            self::LOGIN, self::LOGOUT, self::USER => 'user',
            self::UNIT, self::UNIT_OCCUPANCY => 'unit',
            self::COMMON_AREA => 'common_area',
            self::RESERVATION => 'reservation',
            self::OCCURRENCE => 'occurrence',
            self::NOTICE => 'notice',
        };
    }
}
