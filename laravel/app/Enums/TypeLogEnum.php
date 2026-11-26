<?php

namespace App\Enums;

/**
 * Category of an audit entry, stored in logs.type_log_id. There is no
 * type_logs table: this enum is the reference, and the ids are contract —
 * entries already written point at them. Never write the literal number in the
 * code, and never renumber a case.
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
    case OCCURRENCE_TYPE = 10;
    case SYSTEM = 11;

    /**
     * The kind of record the entries of this type are about, which is what
     * gives logs.subject_id its meaning.
     */
    public function entity(): AuditEntity
    {
        return match ($this) {
            self::LOGIN, self::LOGOUT, self::USER => AuditEntity::User,
            self::UNIT => AuditEntity::Unit,
            self::UNIT_OCCUPANCY => AuditEntity::UnitOccupancy,
            self::COMMON_AREA => AuditEntity::CommonArea,
            self::RESERVATION => AuditEntity::Reservation,
            self::OCCURRENCE => AuditEntity::Occurrence,
            self::OCCURRENCE_TYPE => AuditEntity::OccurrenceType,
            self::NOTICE => AuditEntity::Notice,
            self::SYSTEM => AuditEntity::System,
        };
    }
}
