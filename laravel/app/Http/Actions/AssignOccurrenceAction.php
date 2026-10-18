<?php

namespace App\Http\Actions;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\TypeLogEnum;
use App\Enums\UseCase;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\User;

/**
 * Ocorrencia.encaminhar() do diagrama de classes — RN06 / RN15.
 *
 * Duas portas, decididas por caso de uso e não por papel:
 *
 * - **`assign-occurrence`** — administração e síndico — encaminha qualquer
 *   chamado que a pessoa enxerga, a partir da análise, a qualquer funcionário
 *   que atenda chamados.
 * - **`hand-over-occurrence`** — funcionário — repassa só o chamado que é
 *   dele, atribuído ou já em andamento, a outro funcionário.
 *
 * Repassar — o chamado já tinha responsável — exige justificativa, de quem
 * quer que seja. Quem recebe precisa saber por que o chamado mudou de mãos, e
 * o histórico precisa mostrar quem saiu e por quê. Era decisão pendente da
 * especificação ("se o atendimento iniciado pode ser reatribuído e se a
 * justificativa é obrigatória"); a resposta aqui é sim e sim.
 *
 * O funcionário é obrigatório, embora a especificação diga "opcionalmente".
 * Um chamado Encaminhado sem responsável cairia numa fila que ninguém vê: o
 * funcionário só enxerga o que está atribuído a ele, e o chamado pareceria em
 * atendimento sem ninguém atendendo.
 */
class AssignOccurrenceAction
{
    public static function execute(Occurrence $occurrence, User $author, User $employee, ?string $justification = null): Occurrence
    {
        CheckOccurrenceAccessAction::execute($occurrence, $author);

        self::checkStatus($occurrence);
        self::checkAuthor($occurrence, $author);
        self::checkTarget($occurrence, $employee);

        $anterior = $occurrence->assignedEmployee;
        $justification = trim((string) $justification) ?: null;

        if ($anterior && $justification === null) {
            throw BusinessRuleException::unprocessable(
                "Informe por que o chamado está sendo repassado de {$anterior->name} para {$employee->name}."
            );
        }

        $previousStatus = $occurrence->status;

        $occurrence->update([
            'assigned_employee_id' => $employee->id,
            'status' => OccurrenceStatus::Assigned,
        ]);

        $mensagem = $anterior
            ? "Repassado de {$anterior->name} para {$employee->name}: {$justification}"
            : trim("Encaminhado a {$employee->name}. {$justification}");

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::Assigned,
            $previousStatus,
            OccurrenceStatus::Assigned,
            $mensagem,
        );

        $description = $anterior
            ? "Ocorrência {$occurrence->id} {$occurrence->title} repassada de {$anterior->name} para {$employee->name} por {$author->name}."
            : "Ocorrência {$occurrence->id} {$occurrence->title} encaminhada a {$employee->name} por {$author->name}.";

        CreateLogAction::execute(TypeLogEnum::OCCURRENCE->value, $description, [
            'de' => $anterior?->only(['id', 'name']),
            'para' => $employee->only(['id', 'name']),
            'status_anterior' => $previousStatus->value,
            'justificativa' => $justification,
        ]);

        return $occurrence->refresh();
    }

    /**
     * Encaminha-se a partir da análise; repassa-se o que está atribuído ou em
     * andamento. Aberto ainda não foi lido por ninguém, e encerrado não volta.
     */
    private static function checkStatus(Occurrence $occurrence): void
    {
        if ($occurrence->status->isFinal()) {
            throw BusinessRuleException::conflict("Este chamado já está {$occurrence->status->label()}.");
        }

        // O OccurrenceStatus já recusa aberto → atribuído; a recusa aqui é só
        // para dizer o que fazer em vez de listar estados.
        if ($occurrence->status === OccurrenceStatus::Open) {
            throw BusinessRuleException::conflict('Analise o chamado antes de encaminhá-lo a um funcionário.');
        }
    }

    /**
     * Quem só tem o caso de uso do funcionário repassa apenas o que é seu.
     */
    private static function checkAuthor(Occurrence $occurrence, User $author): void
    {
        if ($author->hasUseCase(UseCase::AssignOccurrence)) {
            return;
        }

        if (! $author->hasUseCase(UseCase::HandOverOccurrence)) {
            throw BusinessRuleException::forbidden('Você não tem autorização para encaminhar chamados.');
        }

        if ($occurrence->assigned_employee_id !== $author->id) {
            throw BusinessRuleException::forbidden('Você só pode repassar os chamados atribuídos a você.');
        }
    }

    /**
     * Quem recebe precisa poder atender: estar ativo e ter o caso de uso de
     * atualizar o andamento. Conferir o caso de uso, e não o papel, faz uma
     * concessão individual valer também aqui.
     */
    private static function checkTarget(Occurrence $occurrence, User $employee): void
    {
        if (! $employee->isActive()) {
            throw BusinessRuleException::unprocessable("{$employee->name} está inativo e não pode receber chamados.");
        }

        if (! $employee->hasUseCase(UseCase::UpdateOccurrenceProgress)) {
            throw BusinessRuleException::unprocessable("{$employee->name} não atende chamados: escolha um funcionário.");
        }

        if ($occurrence->assigned_employee_id === $employee->id) {
            throw BusinessRuleException::conflict("{$employee->name} já é o responsável por este chamado.");
        }
    }
}
