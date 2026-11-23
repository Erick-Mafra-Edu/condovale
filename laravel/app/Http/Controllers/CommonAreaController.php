<?php

namespace App\Http\Controllers;

use App\Http\Actions\ListAvailabilityAction;
use App\Http\Actions\ListOccupancyAction;
use App\Http\Requests\CommonAreaOccupancyRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\CommonArea;
use App\Services\MessageService;

/**
 * UC03 / RF03 — what a resident consults before booking a common area.
 *
 * Both queries are anonymised (RN14): they answer when the area is taken,
 * never by whom.
 */
class CommonAreaController extends Controller
{
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
