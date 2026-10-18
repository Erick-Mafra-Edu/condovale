<?php

namespace App\Http\Controllers;

use App\Enums\OccurrenceStatus;
use App\Enums\UseCase;
use App\Enums\UserStatus;
use App\Http\Actions\AssignOccurrenceAction;
use App\Http\Actions\CreateOccurrenceAction;
use App\Http\Actions\FinishOccurrenceAction;
use App\Http\Actions\UpdateOccurrenceStatusAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\OccurrenceAssignRequest;
use App\Http\Requests\OccurrenceFinishRequest;
use App\Http\Requests\OccurrenceStoreRequest;
use App\Http\Requests\OccurrenceUpdateRequest;
use App\Http\Utils\AuthUtil;
use App\Http\Utils\SanitizeUtil;
use App\Models\Occurrence;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * UC04 / RF04 — abertura, andamento e encerramento de chamados.
 *
 * Quem age vem da sessão, nunca do corpo. Quem pode fazer o quê depende do
 * caso de uso **e** do registro — o funcionário só mexe no chamado que é dele,
 * e só encerra o tipo que permite —, e por isso a decisão fina mora nas
 * Actions. O grupo de rota garante só a porta de entrada.
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

    public function updateStatus(OccurrenceUpdateRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $occurrence = UpdateOccurrenceStatusAction::execute(
                $occurrence,
                AuthUtil::user(),
                OccurrenceStatus::from($item->status),
                $item->message ?? null,
            );

            DB::commit();

            return MessageService::success(
                "Ocorrência {$occurrence->id} agora está {$occurrence->status->label()}.",
                $occurrence
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    public function finish(OccurrenceFinishRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $occurrence = FinishOccurrenceAction::execute(
                $occurrence,
                AuthUtil::user(),
                (bool) $item->resolved,
                $item->conclusion,
            );

            DB::commit();

            $como = $occurrence->resolved ? 'resolvida' : 'encerrada sem solução';

            return MessageService::success(
                "Ocorrência {$occurrence->id} {$como}.",
                $occurrence->load('occurrenceType')
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Ocorrencia.encaminhar(): leva o chamado a um funcionário, ou o repassa.
     *
     * A Action decide se quem pede pode, a partir de que etapa e se a
     * justificativa é obrigatória; aqui só se resolve o chamado e o
     * funcionário da requisição.
     */
    public function assign(OccurrenceAssignRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        $employee = User::find(SanitizeUtil::sanitizeInt($item->employee_id));

        try {
            DB::beginTransaction();

            $occurrence = AssignOccurrenceAction::execute($occurrence, AuthUtil::user(), $employee, $item->message ?? null);

            DB::commit();

            return MessageService::success(
                "Ocorrência {$occurrence->id} encaminhada a {$employee->name}.",
                $occurrence->load(['occurrenceType', 'assignedEmployee:id,name'])
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    /**
     * Quem pode receber um chamado encaminhado: funcionários ativos com o caso
     * de uso de atender.
     *
     * Existe porque a listagem de usuários é do administrador, e o síndico e
     * o funcionário também encaminham — sem esta lista não teriam como
     * escolher para quem. Só sai id e nome: é o que o seletor precisa, e o
     * e-mail dos colegas não é da conta de quem está repassando um chamado.
     */
    public function assignableEmployees(DefaultPaginationRequest $request)
    {
        $item = (object) $request->validated();

        $employees = User::permission(UseCase::UpdateOccurrenceProgress->value)
            ->where('status', UserStatus::Active->value)
            // Ninguém encaminha para si mesmo.
            ->whereKeyNot(AuthUtil::user()->id)
            ->when($item->search ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->orderBy('id')
            ->select(['id', 'name'])
            ->paginate($item->limit ?? 50);

        return MessageService::success('Funcionários disponíveis para encaminhamento.', $employees, true);
    }
}
