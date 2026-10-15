<?php

namespace App\Http\Actions;

use App\Enums\UseCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * RN06 — restricts the listing to what each role may see: the resident sees
 * their own occurrences, the employee sees the ones assigned to them and the
 * administration sees the whole queue.
 */
class ScopeOccurrencesAction
{
    public static function execute(Builder $query, User $user): Builder
    {
        // Quem pode dar baixa em qualquer chamado precisa enxergar todos: o
        // síndico recebeu `close-any-occurrence` sem `analyze-occurrences`, e
        // sem esta linha a listagem lhe mostraria só os chamados em que ele
        // mesmo é o morador — ou seja, nenhum.
        if ($user->hasUseCase(UseCase::AnalyzeOccurrences) || $user->hasUseCase(UseCase::CloseAnyOccurrence)) {
            return $query;
        }

        if ($user->hasUseCase(UseCase::ViewAssignedOccurrences)) {
            return $query->where('assigned_employee_id', $user->id);
        }

        return $query->where('resident_id', $user->id);
    }
}
