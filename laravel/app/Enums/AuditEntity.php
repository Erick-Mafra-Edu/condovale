<?php

namespace App\Enums;

/**
 * The kind of record an audit entry is about. Together with logs.subject_id it
 * identifies the record: "reservation 42", "occurrence_type 3".
 *
 * Exposed by the audit report (RN16) as `entity`, with the same values the
 * frontend uses in AuditLog.entity.
 */
enum AuditEntity: string
{
    case User = 'user';
    case Unit = 'unit';
    case UnitOccupancy = 'unit_occupancy';
    case CommonArea = 'common_area';
    case Reservation = 'reservation';
    case Occurrence = 'occurrence';
    case OccurrenceType = 'occurrence_type';
    case Notice = 'notice';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Usuário',
            self::Unit => 'Unidade',
            self::UnitOccupancy => 'Vínculo de morador',
            self::CommonArea => 'Área comum',
            self::Reservation => 'Reserva',
            self::Occurrence => 'Ocorrência',
            self::OccurrenceType => 'Tipo de ocorrência',
            self::Notice => 'Comunicado',
            self::System => 'Sistema',
        };
    }

    /**
     * The type_log ids filed under this entity, for filtering the trail.
     *
     * @return list<int>
     */
    public function typeLogIds(): array
    {
        return array_values(array_map(
            fn (TypeLogEnum $type) => $type->value,
            array_filter(TypeLogEnum::cases(), fn (TypeLogEnum $type) => $type->entity() === $this)
        ));
    }
}
