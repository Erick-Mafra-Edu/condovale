<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Models\Notice;
use App\Models\User;

/**
 * RN03 — excluir um comunicado oficial, prerrogativa do síndico e da
 * administração.
 *
 * A exclusão apaga a linha, mas não o registro de que o comunicado existiu:
 * título, texto, autor e datas vão inteiros para a auditoria. Um aviso
 * oficial que some sem rastro é exatamente o tipo de coisa que gera disputa
 * em condomínio ("ninguém avisou").
 */
class DeleteNoticeAction
{
    public static function execute(Notice $notice, User $author): void
    {
        $copia = [
            'title' => $notice->title,
            'content' => $notice->content,
            'status' => $notice->status->value,
            'author_id' => $notice->author_id,
            'published_at' => $notice->published_at?->toIso8601String(),
        ];

        $notice->delete();

        CreateLogAction::execute(
            TypeLogEnum::NOTICE->value,
            "Comunicado {$notice->id} {$notice->title} excluído por {$author->name}.",
            $copia
        );
    }
}
