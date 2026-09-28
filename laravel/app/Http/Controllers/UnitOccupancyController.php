<?php

namespace App\Http\Controllers;

use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\LinkResidentToUnitAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\UnitOccupancyFinishRequest;
use App\Http\Requests\UnitOccupancyStoreRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\UnitOccupancy;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * Vínculo entre morador e unidade (associação Usuario-Unidade).
 */
class UnitOccupancyController extends Controller
{
    public function index(DefaultPaginationRequest $request, UnitOccupancy $unitOccupancy)
    {
        $item = (object) $request->validated();
        $searchColumns = ['occupant_type'];

        $response = HandlePaginationAction::execute($request, $unitOccupancy, $searchColumns);
        $occupancies = $response->paginate($item->limit ?? 10);

        return MessageService::success('Vínculos retornados.', $occupancies, true);
    }

    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $occupancy = UnitOccupancy::find($id);

        if (! $occupancy) {
            return MessageService::error("Vínculo {$id} não encontrado.", 404);
        }

        return MessageService::success("Vínculo {$occupancy->id} encontrado.", $occupancy);
    }

    public function store(UnitOccupancyStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $occupancy = LinkResidentToUnitAction::execute($request->validated());

            DB::commit();

            return MessageService::success('Morador vinculado à unidade.', $occupancy);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Encerra o vínculo. Não é exclusão: a linha permanece como histórico de
     * quem ocupou a unidade e até quando.
     */
    public function destroy(UnitOccupancyFinishRequest $request, int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $occupancy = UnitOccupancy::find($id);

        if (! $occupancy) {
            return MessageService::error("Vínculo {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            $occupancy = LinkResidentToUnitAction::finish($occupancy, $request->validated()['ended_at'] ?? null);

            DB::commit();

            return MessageService::success('Vínculo encerrado.', $occupancy);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
