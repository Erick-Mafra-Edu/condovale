<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Enums\TypeLogEnum;
use App\Models\OccurrenceType;

/**
 * Edita o tipo: nome, descrição e situação.
 *
 * Inativar é por aqui, com `active: false`, e é o caminho para tirar de
 * circulação um tipo já usado — que não pode ser apagado. Reativar é o mesmo
 * campo com `true`.
 *
 * Renomear muda o rótulo dos chamados antigos junto, porque eles apontam para
 * o tipo e não guardam cópia do nome. É o comportamento desejado: corrigir
 * "Barulo" para "Barulho" deve corrigir o histórico inteiro, não deixar duas
 * grafias convivendo no relatório.
 */
class UpdateOccurrenceTypeAction
{
    /** What the audit trail compares before and after. */
    private const AUDITADOS = ['name', 'description', 'kind', 'employee_can_close', 'active'];

    /**
     * @param  array{name?: string, description?: string|null, kind?: string, active?: bool}  $attributes
     */
    public static function execute(OccurrenceType $type, array $attributes): OccurrenceType
    {
        $anterior = $type->only(self::AUDITADOS);

        if (isset($attributes['name'])) {
            $attributes['name'] = trim($attributes['name']);
        }

        $type->update($attributes);

        $description = "Tipo de ocorrência {$type->id} {$type->name} editado.";
        CreateLogAction::execute(
            TypeLogEnum::OCCURRENCE_TYPE->value,
            $description,
            [
                'de' => $anterior,
                'para' => $type->only(self::AUDITADOS),
            ],
            action: AuditAction::OccurrenceTypeUpdated,
            subjectId: $type->id,
        );

        return $type->refresh();
    }
}
