<?php

namespace App\Http\Actions;

use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\User;

/**
 * RN06 — a pessoa enxerga este chamado?
 *
 * Reaproveita o ScopeOccurrencesAction, que é quem já define o que cada um vê
 * na listagem: o morador vê os próprios, o funcionário os atribuídos a ele, o
 * síndico e a administração a fila inteira. Ter uma segunda regra aqui seria
 * ter duas respostas para a mesma pergunta — e elas divergiriam na primeira
 * mudança de uma delas.
 */
class CheckOccurrenceAccessAction
{
    public static function execute(Occurrence $occurrence, User $user): void
    {
        $enxerga = ScopeOccurrencesAction::execute(Occurrence::query()->whereKey($occurrence->id), $user)->exists();

        if (! $enxerga) {
            throw BusinessRuleException::forbidden('Você não tem acesso a este chamado.');
        }
    }
}
