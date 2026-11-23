<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Models\CommonArea;

/**
 * Removes a common area that was never booked.
 *
 * The reservations table deletes in cascade with the area, so removing one
 * with reservations would erase the history the reports read — including
 * cancelled and rejected ones. Such an area is made unavailable instead.
 */
class DeleteCommonAreaAction
{
    public static function execute(CommonArea $commonArea): void
    {
        $reservations = $commonArea->reservations()->count();

        if ($reservations > 0) {
            $plural = $reservations === 1 ? 'reserva' : 'reservas';

            throw BusinessRuleException::conflict(
                "A área comum {$commonArea->name} tem {$reservations} {$plural} no histórico: torne-a indisponível em vez de excluí-la, para não apagar as reservas."
            );
        }

        $description = "Área comum {$commonArea->id} {$commonArea->name} excluída.";
        $copy = $commonArea->toArray();

        $commonArea->delete();

        CreateLogAction::execute(TypeLogEnum::COMMON_AREA->value, $description, $copy);
    }
}
