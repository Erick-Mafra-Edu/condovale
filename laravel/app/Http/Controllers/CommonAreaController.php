<?php

namespace App\Http\Controllers;

use App\Http\Actions\CreateCommonAreaAction;
use App\Http\Actions\DeleteCommonAreaAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\ListAvailabilityAction;
use App\Http\Actions\ListOccupancyAction;
use App\Http\Actions\UpdateCommonAreaAction;
use App\Http\Requests\CommonAreaOccupancyRequest;
use App\Http\Requests\CommonAreaStoreRequest;
use App\Http\Requests\CommonAreaUpdateRequest;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\CommonArea;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * AreaComum — the administration keeps the areas (UC03 / RF03) and the
 * residents consult them before booking.
 *
 * Reading is open to whoever books or manages reservations; writing is the
 * administration's, through `manage-reservations`. The route group decides.
 */
class CommonAreaController extends Controller
{
    /**
     * The list is short and the reservation screen asks for all of it at
     * once, without a page size: the default here is the ceiling, in name
     * order. `relations` is dropped: through it would come every reservation
     * of the area, with who booked it (RN14).
     */
    public function index(DefaultPaginationRequest $request, CommonArea $commonArea)
    {
        $item = (object) $request->validated();
        $request->query->remove('relations');

        if (! $request->filled('sort')) {
            $request->query->add(['sort' => 'name', 'direction' => 'asc']);
        }

        $response = HandlePaginationAction::execute($request, $commonArea, ['name', 'description']);
        $commonAreas = $response->paginate($item->limit ?? DefaultPaginationRequest::LIMITE_MAXIMO);

        return MessageService::success('Áreas comuns retornadas.', $commonAreas, true);
    }

    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $commonArea = CommonArea::find($id);

        if (! $commonArea) {
            return MessageService::error("Área comum {$id} não encontrada.", 404);
        }

        return MessageService::success("Área comum {$commonArea->id} encontrada.", $commonArea);
    }

    public function store(CommonAreaStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $commonArea = CreateCommonAreaAction::execute($request->validated());

            DB::commit();

            return MessageService::success("Área comum {$commonArea->id} {$commonArea->name} criada.", $commonArea);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Edits any field, the situation included: `status: unavailable` takes the
     * area out of the calendar and `status: available` brings it back. The
     * message warns when future reservations no longer fit the new rules.
     */
    public function update(CommonAreaUpdateRequest $request, int $id)
    {
        $attributes = $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $commonArea = CommonArea::find($id);

        if (! $commonArea) {
            return MessageService::error("Área comum {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $commonArea = UpdateCommonAreaAction::execute($commonArea, $attributes);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }

        return MessageService::success(UpdateCommonAreaAction::describe($commonArea, $attributes), $commonArea);
    }

    public function destroy(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $commonArea = CommonArea::find($id);

        if (! $commonArea) {
            return MessageService::error("Área comum {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            DeleteCommonAreaAction::execute($commonArea);

            DB::commit();

            return MessageService::success("Área comum {$id} {$commonArea->name} excluída.");
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Day by day: status, free periods, 30 minute blocks and whether a full
     * day booking is possible, already applying RN01 and RN02.
     */
    public function availability(CommonAreaOccupancyRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $commonArea = CommonArea::find($id);

        if (! $commonArea) {
            return MessageService::error("Área comum {$id} não encontrada.", 404);
        }

        try {
            $availability = ListAvailabilityAction::execute($commonArea, $item->start_date, $item->end_date);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        return MessageService::success("Disponibilidade da área {$commonArea->id} {$commonArea->name} retornada.", $availability);
    }

    /**
     * The raw taken periods, for a client that builds its own calendar. Sends
     * an empty list, with the message, when nothing is booked.
     */
    public function occupancy(CommonAreaOccupancyRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $commonArea = CommonArea::find($id);

        if (! $commonArea) {
            return MessageService::error("Área comum {$id} não encontrada.", 404);
        }

        $occupancy = ListOccupancyAction::execute($commonArea, $item->start_date, $item->end_date);

        return MessageService::success("Ocupação da área {$commonArea->id} {$commonArea->name} retornada.", $occupancy);
    }
}
