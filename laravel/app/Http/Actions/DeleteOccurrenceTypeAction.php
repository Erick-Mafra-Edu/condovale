<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Models\OccurrenceType;

/**
 * Apaga um tipo que nunca foi usado.
 *
 * Tipo já referenciado por um chamado não é apagado: os chamados antigos
 * ficariam sem classificação e o relatório do síndico perderia justamente o
 * histórico que ele serve para mostrar. A chave estrangeira também recusaria,
 * mas aí o erro chegaria como falha de banco; aqui a recusa explica o motivo e
 * diz o que fazer no lugar.
 *
 * O caminho para tirar de circulação um tipo em uso é inativá-lo.
 */
class DeleteOccurrenceTypeAction
{
    public static function execute(OccurrenceType $type): void
    {
        $emUso = $type->occurrences()->count();

        if ($emUso > 0) {
            $plural = $emUso === 1 ? 'chamado usa' : 'chamados usam';

            throw BusinessRuleException::conflict(
                "{$emUso} {$plural} o tipo {$type->name}: inative-o em vez de apagá-lo, para não deixar os chamados antigos sem classificação."
            );
        }

        $description = "Tipo de ocorrência {$type->id} {$type->name} excluído.";
        // The row is gone after this: the copy is what remains of it.
        $copy = $type->only(['name', 'description', 'employee_can_close', 'active']);

        $type->delete();

        CreateLogAction::execute(TypeLogEnum::OCCURRENCE_TYPE->value, $description, $copy, action: AuditAction::OccurrenceTypeDeleted, subjectId: $type->id);
    }
}
