<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Enums\NoticeStatus;
use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Models\Notice;
use App\Models\User;

/**
 * RF05 — publicar um rascunho (Comunicado.publicar()).
 *
 * Sem esta porta, um comunicado guardado como rascunho não teria como chegar
 * aos moradores. A data de publicação é a de agora, e não a de quando o
 * rascunho foi escrito: é a partir daqui que o morador passa a ver o aviso.
 * O autor continua sendo quem escreveu; quem publicou fica na auditoria.
 */
class PublishNoticeAction
{
    public static function execute(Notice $notice, User $publisher): Notice
    {
        if ($notice->status === NoticeStatus::Published) {
            throw BusinessRuleException::conflict("O comunicado {$notice->id} já está publicado.");
        }

        $notice->update([
            'status' => NoticeStatus::Published,
            'published_at' => now(),
        ]);

        CreateLogAction::execute(
            TypeLogEnum::NOTICE->value,
            "Comunicado {$notice->id} {$notice->title} publicado por {$publisher->name}.",
            action: AuditAction::NoticePublished,
            subjectId: $notice->id,
        );

        return $notice;
    }
}
