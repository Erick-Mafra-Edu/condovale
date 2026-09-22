<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\User;

/**
 * UC8/RN06 — the administration forwards the occurrence to an active employee.
 *
 * Reassigning an attendance that already started is refused: the
 * specification has not defined whether it is allowed nor whether a
 * justification would be required.
 */
class AssignOccurrenceAction
{
    public static function execute(Occurrence $occurrence, User $author, User $employee): Occurrence
    {
        if ($employee->role !== UserRole::Employee || ! $employee->isActive()) {
            throw BusinessRuleException::unprocessable('A ocorrência só pode ser atribuída a um funcionário ativo.');
        }

        if ($occurrence->status->isFinal()) {
            throw BusinessRuleException::conflict('Uma ocorrência encerrada não pode ser atribuída.');
        }

        if ($occurrence->status === OccurrenceStatus::InProgress) {
            throw BusinessRuleException::conflict('Não é possível reatribuir um atendimento já iniciado.');
        }

        $previousStatus = $occurrence->status;

        $occurrence->update([
            'assigned_employee_id' => $employee->id,
            'status' => OccurrenceStatus::Assigned,
        ]);

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::Assigned,
            $previousStatus,
            OccurrenceStatus::Assigned,
            "Atendimento atribuído a {$employee->name}.",
        );

        return $occurrence->refresh();
    }
}
