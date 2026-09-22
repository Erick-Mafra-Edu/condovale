<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\User;

/**
 * RN06 — only authorized users change the status of an occurrence.
 *
 * The lifecycle of the specification is open -> analysis -> assigned ->
 * in_progress -> completed. The administration analyses and distributes; the
 * assigned employee executes.
 *
 * Provisional decision: cancelling is allowed to the administration while the
 * attendance has not started, because the specification does not say who may
 * cancel an occurrence (see frontend/docs/requirements-compliance.md).
 */
class UpdateOccurrenceStatusAction
{
    public static function execute(
        Occurrence $occurrence,
        User $author,
        OccurrenceStatus $status,
        ?string $message = null,
    ): Occurrence {
        self::checkAuthor($occurrence, $author);
        self::checkTransition($occurrence, $author, $status);

        $previousStatus = $occurrence->status;

        $occurrence->update([
            'status' => $status,
            'completed_at' => $status === OccurrenceStatus::Completed ? now() : $occurrence->completed_at,
        ]);

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::StatusChanged,
            $previousStatus,
            $status,
            $message,
        );

        return $occurrence->refresh();
    }

    /**
     * An employee only acts on the occurrence assigned to them.
     */
    public static function checkAuthor(Occurrence $occurrence, User $author): void
    {
        if ($author->role === UserRole::Employee && $occurrence->assigned_employee_id !== $author->id) {
            throw BusinessRuleException::forbidden('Você só pode atender as ocorrências atribuídas a você.');
        }
    }

    private static function checkTransition(Occurrence $occurrence, User $author, OccurrenceStatus $status): void
    {
        $current = $occurrence->status;

        if ($current === $status) {
            throw BusinessRuleException::conflict('A ocorrência já está neste status.');
        }

        if ($current->isFinal()) {
            throw BusinessRuleException::conflict('Uma ocorrência encerrada não pode mudar de status.');
        }

        $allowed = match ($author->role) {
            UserRole::Admin => match ($current) {
                OccurrenceStatus::Open => [OccurrenceStatus::Analysis, OccurrenceStatus::Cancelled],
                OccurrenceStatus::Analysis, OccurrenceStatus::Assigned => [OccurrenceStatus::Cancelled],
                default => [],
            },
            UserRole::Employee => match ($current) {
                OccurrenceStatus::Assigned => [OccurrenceStatus::InProgress],
                OccurrenceStatus::InProgress => [OccurrenceStatus::Completed],
                default => [],
            },
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw BusinessRuleException::conflict(
                "Transição não permitida para este usuário: {$current->value} para {$status->value}."
            );
        }
    }
}
