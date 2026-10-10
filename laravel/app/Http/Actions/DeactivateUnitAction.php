<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Enums\UnitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Unit;

/**
 * RN03 — alteração estrutural: a unidade sai de operação sem ser apagada.
 *
 * Apagar não é opção: reservas, ocorrências e o histórico de ocupação apontam
 * para a unidade, e o condomínio ficaria com buracos no próprio passado.
 */
class DeactivateUnitAction
{
    public static function execute(Unit $unit): Unit
    {
        self::checkCanDeactivate($unit);

        $unit->update(['status' => UnitStatus::Inactive->value]);

        $description = "Unidade {$unit->id} {$unit->code} inativada.";
        CreateLogAction::execute(TypeLogEnum::UNIT->value, $description);

        return $unit->refresh();
    }

    /**
     * Vale também para o PATCH que manda `status: inactive`: a porta fechada
     * numa rota não pode ficar aberta na outra.
     */
    public static function checkCanDeactivate(Unit $unit): void
    {
        if (! $unit->isActive()) {
            throw BusinessRuleException::conflict('Esta unidade já está inativa.');
        }

        self::checkSemMoradores($unit);
    }

    /**
     * Unidade inativa não aceita vínculo novo — a LinkResidentToUnitAction
     * recusa. Inativar uma que ainda tem morador criaria o estado que o
     * sistema não deixa criar: alguém vinculado a uma unidade fora de
     * operação, sem como refazer o vínculo depois.
     *
     * Por isso a ordem é encerrar os vínculos primeiro, e a mensagem diz
     * quantos são para a administração saber o tamanho do trabalho.
     */
    private static function checkSemMoradores(Unit $unit): void
    {
        $vigentes = $unit->activeOccupancies()->count();

        if ($vigentes > 0) {
            $plural = $vigentes === 1 ? 'morador vinculado' : 'moradores vinculados';

            throw BusinessRuleException::conflict(
                "A unidade {$unit->code} ainda tem {$vigentes} {$plural}: encerre os vínculos antes de inativá-la."
            );
        }
    }
}
