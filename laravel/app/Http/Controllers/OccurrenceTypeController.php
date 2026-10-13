<?php

namespace App\Http\Controllers;

use App\Http\Actions\CreateOccurrenceTypeAction;
use App\Http\Actions\DeleteOccurrenceTypeAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\UpdateOccurrenceTypeAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\OccurrenceTypeStoreRequest;
use App\Http\Requests\OccurrenceTypeUpdateRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\OccurrenceType;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * RF04 — a administração mantém a lista de tipos de chamado.
 *
 * A listagem devolve ativos e inativos: quem administra precisa enxergar os
 * aposentados para reativá-los. Quem está montando a lista de escolha do
 * morador filtra com `filters=active:=:1`.
 */
class OccurrenceTypeController extends Controller
{
    public function index(DefaultPaginationRequest $request, OccurrenceType $occurrenceType)
    {
        $item = (object) $request->validated();
        $searchColumns = ['name', 'description'];

        $response = HandlePaginationAction::execute($request, $occurrenceType, $searchColumns);
        $types = $response->paginate($item->limit ?? 10);

        return MessageService::success('Tipos de ocorrência retornados.', $types, true);
    }

    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $type = OccurrenceType::find($id);

        if (! $type) {
            return MessageService::error("Tipo de ocorrência {$id} não encontrado.", 404);
        }

        return MessageService::success("Tipo de ocorrência {$type->id} encontrado.", $type);
    }

    public function store(OccurrenceTypeStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $type = CreateOccurrenceTypeAction::execute($request->validated());

            DB::commit();

            return MessageService::success("Tipo de ocorrência {$type->name} criado.", $type);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    public function update(OccurrenceTypeUpdateRequest $request, int $id)
    {
        $attributes = $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $type = OccurrenceType::find($id);

        if (! $type) {
            return MessageService::error("Tipo de ocorrência {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            $type = UpdateOccurrenceTypeAction::execute($type, $attributes);

            DB::commit();

            return MessageService::success("Tipo de ocorrência {$type->name} editado.", $type);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    public function destroy(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $type = OccurrenceType::find($id);

        if (! $type) {
            return MessageService::error("Tipo de ocorrência {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            $nome = $type->name;
            DeleteOccurrenceTypeAction::execute($type);

            DB::commit();

            return MessageService::success("Tipo de ocorrência {$nome} excluído.");
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
