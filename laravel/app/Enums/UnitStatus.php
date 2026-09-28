<?php

namespace App\Enums;

/**
 * Situação da unidade (Unidade.ativa no diagrama de classes).
 *
 * A unidade é inativada, nunca excluída: reservas, ocorrências e o histórico
 * de ocupação continuam apontando para ela.
 */
enum UnitStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativa',
            self::Inactive => 'Inativa',
        };
    }
}
