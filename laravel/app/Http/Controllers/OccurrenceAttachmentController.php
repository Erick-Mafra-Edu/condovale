<?php

namespace App\Http\Controllers;

use App\Http\Actions\CheckOccurrenceAccessAction;
use App\Http\Actions\DeleteOccurrenceAttachmentAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\StoreOccurrenceAttachmentAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\OccurrenceAttachmentStoreRequest;
use App\Http\Utils\AuthUtil;
use App\Http\Utils\SanitizeUtil;
use App\Models\Occurrence;
use App\Models\OccurrenceAttachment;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RF04 — imagens anexadas ao chamado.
 *
 * Toda rota confere, antes de qualquer coisa, se a pessoa enxerga o chamado:
 * quem não vê o chamado não vê, não lista e não envia imagem para ele.
 */
class OccurrenceAttachmentController extends Controller
{
    public function index(DefaultPaginationRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        try {
            CheckOccurrenceAccessAction::execute($occurrence, AuthUtil::user());
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        $response = HandlePaginationAction::execute($request, new OccurrenceAttachment, ['original_name']);
        $attachments = $response->where('occurrence_id', $occurrence->id)->paginate($item->limit ?? 10);

        return MessageService::success("Imagens da ocorrência {$occurrence->id} retornadas.", $attachments, true);
    }

    public function store(OccurrenceAttachmentStoreRequest $request, int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $attachment = StoreOccurrenceAttachmentAction::execute($occurrence, AuthUtil::user(), $request->file('image'));

            DB::commit();

            return MessageService::success("Imagem anexada à ocorrência {$occurrence->id}.", $attachment);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    public function destroy(int $id, int $attachment)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $attachmentId = SanitizeUtil::sanitizeInt($attachment);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $removed = DeleteOccurrenceAttachmentAction::execute($occurrence, AuthUtil::user(), $attachmentId);

            DB::commit();

            return MessageService::success("Imagem {$removed->id} removida da ocorrência {$occurrence->id}.");
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Entrega a imagem.
     *
     * É a exceção à regra de que toda resposta sai pelo MessageService: o
     * corpo aqui é o arquivo, para a tag <img> exibir direto. Os erros
     * continuam saindo pelo envelope.
     *
     * Os cabeçalhos fecham as portas que um arquivo enviado por usuário pode
     * abrir: `nosniff` impede o navegador de tratar a imagem como outra coisa,
     * a CSP impede que ela execute qualquer conteúdo ativo, e `private` impede
     * que um proxy no caminho guarde uma imagem que exige login.
     */
    public function show(int $id, int $attachment)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $attachmentId = SanitizeUtil::sanitizeInt($attachment);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        try {
            CheckOccurrenceAccessAction::execute($occurrence, AuthUtil::user());
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        // O id da imagem só vale dentro do chamado da rota: sem esta
        // amarração, quem enxerga um chamado leria imagens de qualquer outro
        // trocando o número no fim do endereço.
        $arquivo = OccurrenceAttachment::where('occurrence_id', $occurrence->id)->find($attachmentId);

        if (! $arquivo) {
            return MessageService::error("Imagem {$attachmentId} não encontrada nesta ocorrência.", 404);
        }

        $disco = Storage::disk(config('uploads.occurrence_attachments.disk'));

        if (! $disco->exists($arquivo->path)) {
            return MessageService::error("O arquivo da imagem {$arquivo->id} não está mais no servidor.", 404);
        }

        return $disco->response($arquivo->path, $arquivo->original_name, [
            'Content-Type' => $arquivo->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }
}
