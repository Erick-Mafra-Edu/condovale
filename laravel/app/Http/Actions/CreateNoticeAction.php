<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Enums\NoticeStatus;
use App\Enums\TypeLogEnum;
use App\Models\Notice;
use App\Models\User;

/**
 * RF05 / RN07 — publicar um comunicado oficial, ou guardá-lo como rascunho.
 *
 * O autor é sempre quem está na sessão, nunca o corpo da requisição: aceitar
 * o autor de fora deixaria qualquer um assinar um comunicado em nome do
 * síndico. A data de publicação também é do servidor, pelo mesmo motivo — um
 * comunicado com data retroativa pareceria ter sido avisado antes.
 */
class CreateNoticeAction
{
    /**
     * @param  array{title: string, content: string, status?: string}  $attributes
     */
    public static function execute(User $author, array $attributes): Notice
    {
        $status = NoticeStatus::from($attributes['status'] ?? NoticeStatus::Published->value);

        $notice = Notice::create([
            'title' => $attributes['title'],
            'content' => $attributes['content'],
            'author_id' => $author->id,
            'status' => $status,
            'published_at' => $status === NoticeStatus::Published ? now() : null,
        ]);

        $description = $status === NoticeStatus::Published
            ? "Comunicado {$notice->id} {$notice->title} publicado por {$author->name}."
            : "Comunicado {$notice->id} {$notice->title} salvo como rascunho por {$author->name}.";

        CreateLogAction::execute(
            TypeLogEnum::NOTICE->value,
            $description,
            ['status' => $status->value],
            action: $status === NoticeStatus::Published ? AuditAction::NoticePublished : AuditAction::NoticeDrafted,
            subjectId: $notice->id,
        );

        return $notice;
    }
}
