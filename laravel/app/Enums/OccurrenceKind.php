<?php

namespace App\Enums;

/**
 * The nature of an occurrence type, which is what tells UC6 from UC7.
 *
 * RF04 has two use cases for opening a ticket: "UC6 - Registrar ocorrência"
 * (a problem in the building) and "UC7 - Registrar denúncia" (a complaint
 * about conduct: noise, misuse of a common area). Both are tickets bound to
 * the resident who opened them, with a status (RN05), handled by the same
 * flow (RN06); what differs is the subject, so the nature lives in the type
 * the resident chooses, and the administration decides which types are
 * complaints.
 */
enum OccurrenceKind: string
{
    case Maintenance = 'maintenance';
    case Complaint = 'complaint';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Manutenção',
            self::Complaint => 'Denúncia',
        };
    }

    /** For the `in:` validation rule. */
    public static function values(): string
    {
        return implode(',', array_map(fn (self $kind) => $kind->value, self::cases()));
    }
}
