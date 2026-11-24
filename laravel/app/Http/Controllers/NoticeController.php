<?php

namespace App\Http\Controllers;

use App\Enums\NoticeStatus;
use App\Http\Actions\CreateNoticeAction;
use App\Http\Actions\DeleteNoticeAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\PublishNoticeAction;
use App\Http\Actions\ScopeNoticesAction;
use App\Http\Actions\UpdateNoticeAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\NoticeStoreRequest;
use App\Http\Requests\NoticeUpdateRequest;
use App\Http\Utils\AuthUtil;
use App\Http\Utils\SanitizeUtil;
use App\Models\Notice;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * UC05 / RF05 — mural de comunicados oficiais.
 *
 * Ler é de quem tem `view-notices` ou `publish-notices`; publicar e excluir,
 * só de quem tem `publish-notices` (RN07 e RN03). Quem decide é o grupo de
 * rota. O que cada um enxerga — rascunho ou não — é a ScopeNoticesAction.
 */
class NoticeController extends Controller
{
    /** Do autor saem id e nome; o e-mail de quem publica não é do mural. */
    private const AUTOR = 'author:id,name';

    /**
     * RN18 — do mais recente para o mais antigo, a não ser que o cliente peça
     * outra ordem. O parâmetro `relations` é descartado: por ele sairia o
     * cadastro inteiro do autor.
     */
    public function index(DefaultPaginationRequest $request)
    {
        $item = (object) $request->validated();
        $request->query->remove('relations');

        if (! $request->filled('sort')) {
            $request->query->add(['sort' => 'published_at', 'direction' => 'desc']);
        }

        $response = HandlePaginationAction::execute($request, new Notice, ['title', 'content']);
        $notices = ScopeNoticesAction::execute($response, AuthUtil::user())
            ->with(self::AUTOR)
            ->paginate($item->limit ?? 10);

        return MessageService::success('Comunicados retornados.', $notices, true);
    }

    /**
     * Um rascunho responde 404 para quem não publica, igual a um comunicado
     * que não existe: a diferença revelaria que há um aviso em preparação.
     */
    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $notice = ScopeNoticesAction::execute(Notice::query(), AuthUtil::user())->with(self::AUTOR)->find($id);

        if (! $notice) {
            return MessageService::error("Comunicado {$id} não encontrado.", 404);
        }

        return MessageService::success("Comunicado {$notice->id} encontrado.", $notice);
    }

    public function store(NoticeStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $notice = CreateNoticeAction::execute(AuthUtil::user(), $request->validated());

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }

        $mensagem = $notice->published_at
            ? "Comunicado {$notice->id} publicado."
            : "Comunicado {$notice->id} salvo como rascunho.";

        return MessageService::success($mensagem, $notice->load(self::AUTOR));
    }

    /**
     * Corrects title or content; `status: published` publishes a draft. The
     * previous text stays in the audit trail.
     */
    public function update(NoticeUpdateRequest $request, int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $notice = Notice::find($id);

        if (! $notice) {
            return MessageService::error("Comunicado {$id} não encontrado.", 404);
        }

        $eraRascunho = $notice->status === NoticeStatus::Draft;

        try {
            DB::beginTransaction();

            $notice = UpdateNoticeAction::execute($notice, AuthUtil::user(), $request->validated());

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }

        $mensagem = $eraRascunho && $notice->status === NoticeStatus::Published
            ? "Comunicado {$notice->id} atualizado e publicado."
            : "Comunicado {$notice->id} atualizado.";

        return MessageService::success($mensagem, $notice->load(self::AUTOR));
    }

    public function publish(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $notice = Notice::find($id);

        if (! $notice) {
            return MessageService::error("Comunicado {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            PublishNoticeAction::execute($notice, AuthUtil::user());

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }

        return MessageService::success("Comunicado {$notice->id} publicado.", $notice->load(self::AUTOR));
    }

    public function destroy(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $notice = Notice::find($id);

        if (! $notice) {
            return MessageService::error("Comunicado {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            DeleteNoticeAction::execute($notice, AuthUtil::user());

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }

        return MessageService::success("Comunicado {$id} excluído.");
    }
}
