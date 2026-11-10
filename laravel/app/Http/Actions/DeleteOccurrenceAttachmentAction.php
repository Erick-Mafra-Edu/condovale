<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\TypeLogEnum;
use App\Enums\UseCase;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\OccurrenceAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RF04 — tirar uma imagem do chamado.
 *
 * Quem enviou tira a própria imagem: a foto errada, a duplicada. O síndico e a
 * administração tiram qualquer uma, porque respondem pelo que fica exposto no
 * chamado — a foto que mostra o vizinho, o documento fotografado sem querer. A
 * régua é a mesma da baixa em qualquer chamado (`close-any-occurrence`), que é
 * quem já tem autoridade sobre o chamado inteiro.
 *
 * Só enquanto o chamado está aberto. Concluído e Cancelado são o registro do
 * que foi decidido, e a imagem é parte da evidência: apagá-la depois mudaria
 * o histórico que a auditoria existe para preservar.
 */
class DeleteOccurrenceAttachmentAction
{
    public static function execute(Occurrence $occurrence, User $author, int $attachmentId): OccurrenceAttachment
    {
        // O acesso vem antes da busca: quem não enxerga o chamado não descobre,
        // pela diferença entre 403 e 404, quais imagens ele tem.
        CheckOccurrenceAccessAction::execute($occurrence, $author);

        $attachment = OccurrenceAttachment::where('occurrence_id', $occurrence->id)->find($attachmentId);

        if (! $attachment) {
            throw BusinessRuleException::notFound("Imagem {$attachmentId} não encontrada nesta ocorrência.");
        }

        if ($occurrence->status->isFinal()) {
            throw BusinessRuleException::conflict(
                "Este chamado está {$occurrence->status->label()}: as imagens dele não mudam mais."
            );
        }

        if ($attachment->uploaded_by !== $author->id && ! $author->hasUseCase(UseCase::CloseAnyOccurrence)) {
            throw BusinessRuleException::forbidden('Você só pode excluir as imagens que você enviou.');
        }

        $attachment->delete();

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::Comment,
            null,
            null,
            "Imagem removida: {$attachment->original_name}.",
        );

        $description = "Imagem {$attachment->id} removida da ocorrência {$occurrence->id} {$occurrence->title} por {$author->name}.";
        CreateLogAction::execute(TypeLogEnum::OCCURRENCE->value, $description, [
            'arquivo' => $attachment->original_name,
            'enviada_por' => $attachment->uploaded_by,
        ]);

        // O arquivo sai só depois do commit. Apagado antes, um rollback
        // devolveria a linha apontando para um arquivo que já não existe.
        DB::afterCommit(static fn () => self::dropFile($attachment->path));

        return $attachment;
    }

    /**
     * Se o disco falhar aqui, a imagem já saiu do chamado e não volta: o que
     * sobra é um arquivo sem registro. Ele vai para o log de erros, para quem
     * cuida da conta saber que há um inode a recuperar, mas não desfaz uma
     * exclusão que deu certo.
     */
    private static function dropFile(string $path): void
    {
        try {
            Storage::disk(config('uploads.occurrence_attachments.disk'))->delete($path);
        } catch (\Throwable $th) {
            report($th);
        }
    }
}
