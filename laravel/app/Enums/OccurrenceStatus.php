<?php

namespace App\Enums;

enum OccurrenceStatus: string
{
    case Open = 'open';
    case Analysis = 'analysis';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * Rótulo para as mensagens que chegam ao usuário: "Não é possível mudar o
     * chamado de Aberto para Em andamento" se lê; "open para in_progress", não.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::Analysis => 'Em análise',
            self::Assigned => 'Atribuído',
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }
}
