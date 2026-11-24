<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Enums\NoticeStatus;
use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\AuditUtil;
use App\Models\Notice;
use App\Models\User;

/**
 * RF05 — edits an official notice: title and content.
 *
 * A published notice can be corrected — a wrong date in an assembly call is
 * worse left on the board —, but the previous text is never lost: the audit
 * trail keeps it as before/after. The author stays who wrote it and
 * `published_at` stays the moment it reached the board; who edited is in the
 * trail.
 *
 * `status: published` on a draft publishes it, as POST /publish does. A
 * published notice does not go back to draft: once residents could read it,
 * the way to withdraw it is deleting, which keeps a copy (RN03).
 */
class UpdateNoticeAction
{
    private const EDITABLE = ['title', 'content'];

    /**
     * @param  array{title?: string, content?: string, status?: string}  $attributes
     */
    public static function execute(Notice $notice, User $editor, array $attributes): Notice
    {
        $status = isset($attributes['status']) ? NoticeStatus::from($attributes['status']) : null;

        if ($status === NoticeStatus::Draft && $notice->status === NoticeStatus::Published) {
            throw BusinessRuleException::conflict('Um comunicado publicado não volta a rascunho: exclua-o se ele não vale mais.');
        }

        $notice->fill(array_intersect_key($attributes, array_flip(self::EDITABLE)));
        $changes = AuditUtil::diff($notice);

        if ($changes !== []) {
            $notice->save();

            CreateLogAction::execute(
                TypeLogEnum::NOTICE->value,
                "Comunicado {$notice->id} {$notice->title} editado por {$editor->name}.",
                $changes,
                action: AuditAction::NoticeUpdated,
                subjectId: $notice->id,
            );
        }

        if ($status === NoticeStatus::Published && $notice->status === NoticeStatus::Draft) {
            PublishNoticeAction::execute($notice, $editor);
        }

        return $notice->refresh();
    }
}
