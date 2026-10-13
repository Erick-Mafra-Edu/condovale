<?php

namespace App\Http\Controllers;

use App\Http\Actions\CreateOccurrenceAction;
use App\Http\Requests\OccurrenceStoreRequest;
use App\Http\Utils\AuthUtil;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * UC04 / RF04 / RN05 — abertura de chamado de ocorrência.
 *
 * Quem abre vem da sessão, nunca do corpo. O corpo só diz **para quem** o
 * chamado é, e apenas quem tem o caso de uso de abrir no lugar de outra pessoa
 * pode usar esse campo — quem decide isso é a Action, porque é regra de quem
 * pode agir sobre qual registro, e não de qual rota foi chamada.
 */
class OccurrenceController extends Controller
{
    public function store(OccurrenceStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $occurrence = CreateOccurrenceAction::execute(AuthUtil::user(), $request->validated());

            DB::commit();

            return MessageService::success(
                "Ocorrência {$occurrence->id} aberta.",
                $occurrence->load('occurrenceType')
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
