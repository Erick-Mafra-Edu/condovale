<?php

namespace App\Http\Controllers;

use App\Enums\OccurrenceStatus;
use App\Enums\UseCase;
use App\Enums\UserStatus;
use App\Http\Actions\AssignOccurrenceAction;
use App\Http\Actions\CheckOccurrenceAccessAction;
use App\Http\Actions\CreateOccurrenceAction;
use App\Http\Actions\FinishOccurrenceAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Actions\ScopeOccurrencesAction;
use App\Http\Actions\UpdateOccurrenceAttendanceAction;
use App\Http\Actions\UpdateOccurrenceStatusAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Requests\OccurrenceAssignRequest;
use App\Http\Requests\OccurrenceAttendanceRequest;
use App\Http\Requests\OccurrenceFinishRequest;
use App\Http\Requests\OccurrenceStoreRequest;
use App\Http\Requests\OccurrenceUpdateRequest;
use App\Http\Utils\AuthUtil;
use App\Http\Utils\SanitizeUtil;
use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
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
    /**
     * As relações que acompanham um chamado, sempre com as colunas contadas.
     *
     * O parâmetro genérico `relations` não vale aqui: ele deixaria pedir
     * `resident` inteiro, ou `resident.reservations`, e o funcionário que
     * atende veria o e-mail e as reservas do morador. Das pessoas sai só id e
     * nome — é o que a tela de acompanhamento precisa.
     */
    private const RELACOES = [
        'occurrenceType:id,name,employee_can_close',
        'resident:id,name',
        'assignedEmployee:id,name',
        'unit:id,code,block,number',
    ];

    /**
     * Os chamados que a pessoa enxerga: o morador os próprios, o funcionário
     * os atribuídos a ele, o síndico e a administração a fila inteira.
     */
    public function index(DefaultPaginationRequest $request)
    {
        $item = (object) $request->validated();
        $request->query->remove('relations');

        $response = HandlePaginationAction::execute($request, new Occurrence, ['title', 'description']);
        $occurrences = ScopeOccurrencesAction::execute($response, AuthUtil::user())
            ->with(self::RELACOES)
            ->paginate($item->limit ?? 10);

        return MessageService::success('Ocorrências retornadas.', $occurrences, true);
    }

    /**
     * O chamado com o que a tela de acompanhamento mostra junto: tipo,
     * morador, responsável, unidade e imagens.
     */
    public function show(int $id)
    {
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

        return MessageService::success(
            "Ocorrência {$occurrence->id} encontrada.",
            $occurrence->load([...self::RELACOES, 'attachments'])
        );
    }

    /**
     * A linha do tempo do chamado, da abertura ao desfecho, com quem fez cada
     * coisa.
     */
    public function history(DefaultPaginationRequest $request, int $id)
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

        $request->query->remove('relations');

        $histories = HandlePaginationAction::execute($request, new OccurrenceHistory, ['message'])
            ->where('occurrence_id', $occurrence->id)
            ->with('user:id,name')
            ->paginate($item->limit ?? 50);

        return MessageService::success("Histórico da ocorrência {$occurrence->id} retornado.", $histories, true);
    }

    /**
     * Atualização do atendimento: novo status, anotação de andamento, ou os
     * dois. Quem pode o quê em cada chamado é decidido na Action.
     */
    public function updateAttendance(OccurrenceAttendanceRequest $request, int $id)
    {
        $item = (object) $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $occurrence = Occurrence::find($id);

        if (! $occurrence) {
            return MessageService::error("Ocorrência {$id} não encontrada.", 404);
        }

        try {
            DB::beginTransaction();

            $occurrence = UpdateOccurrenceAttendanceAction::execute(
                $occurrence,
                AuthUtil::user(),
                isset($item->status) ? OccurrenceStatus::from($item->status) : null,
                $item->message ?? null,
            );

            DB::commit();

            return MessageService::success(
                "Atendimento da ocorrência {$occurrence->id} atualizado.",
                $occurrence->load(self::RELACOES)
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

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
