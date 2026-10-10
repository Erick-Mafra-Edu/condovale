<?php

namespace App\Http\Controllers;

use App\Http\Actions\CreateUnitAction;
use App\Http\Actions\DeactivateUnitAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\UpdateUnitAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\UnitStoreRequest;
use App\Http\Requests\UnitUpdateRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\Unit;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * RN03 — as alterações estruturais de unidades.
 *
 * Quem pode chegar aqui é decidido pelo grupo de rota, como em todo o projeto:
 * o controller não verifica permissão.
 */
class UnitController extends Controller
{
    public function index(DefaultPaginationRequest $request, Unit $unit)
    {
        $item = (object) $request->validated();
        $searchColumns = ['code', 'number', 'block'];

        $response = HandlePaginationAction::execute($request, $unit, $searchColumns);
        $units = $response->paginate($item->limit ?? 10);

        return MessageService::success('Unidades retornadas.', $units, true);
    }

    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $unit = Unit::find($id);

        if (! $unit) {
            return MessageService::error("Unidade {$id} não encontrada.", 404);
        }

        return MessageService::success("Unidade {$unit->id} encontrada.", $unit);
    }

    public function store(UnitStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $unit = CreateUnitAction::execute($request->validated());

            DB::commit();

            return MessageService::success("Unidade {$unit->code} cadastrada.", $unit);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Edita os dados da unidade. A reativação é esta rota com
     * `status: active` — não há endpoint próprio para ela.
     */
    public function update(UnitUpdateRequest $request, int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $unit = Unit::find($id);

        if (! $unit) {
            return MessageService::error("Unidade {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $unit = UpdateUnitAction::execute($unit, $request->validated());

            DB::commit();

            return MessageService::success("Unidade {$unit->code} editada.", $unit);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Tira a unidade de operação sem apagá-la: reservas, ocorrências e o
     * histórico de ocupação continuam apontando para ela.
     */
    public function deactivate(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $unit = Unit::find($id);

        if (! $unit) {
            return MessageService::error("Unidade {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $unit = DeactivateUnitAction::execute($unit);

            DB::commit();

            return MessageService::success("Unidade {$unit->code} inativada.", $unit);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
