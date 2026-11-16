<?php

namespace App\Http\Actions;

use App\Enums\NoticeStatus;
use App\Enums\UseCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * RN18 — quem enxerga o quê no mural de comunicados.
 *
 * Quem publica (síndico e administração) vê também os rascunhos, porque é
 * quem os prepara. Quem só lê vê apenas o que foi publicado: um rascunho é
 * texto em revisão, e mostrá-lo ao morador seria publicar sem querer.
 */
class ScopeNoticesAction
{
    public static function execute(Builder $query, User $user): Builder
    {
        if ($user->hasUseCase(UseCase::PublishNotices)) {
            return $query;
        }

        return $query->where('status', NoticeStatus::Published->value);
    }
}
