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
 * RN06 — só quem tem autorização muda o status do chamado.
 *
 * O fluxo em si — que transições existem — é do OccurrenceStatus, e o model
 * o confere em toda gravação. Esta Action decide **quem** faz cada transição:
 * a administração analisa e pode cancelar enquanto o atendimento não começou;
 * o funcionário atribuído inicia o atendimento (RN15).
 *
 * Duas transições não passam por aqui, de propósito:
 *
 * - **Concluir** vai pela FinishOccurrenceAction, que exige a conclusão e se o
 *   problema foi resolvido. Deixar a conclusão também por aqui seria uma porta
 *   para encerrar chamado sem dizer como terminou.
 * - **Atribuir** vai pela AssignOccurrenceAction, que exige o funcionário.
 *   Status "atribuído" sem ninguém atribuído seria um chamado que ninguém
 *   atende e que parece estar sendo atendido.
 *
 * As transições são decididas pelos **casos de uso**, e não pela coluna
 * `users.role`. A versão anterior olhava o papel, e por isso ignorava as
 * concessões individuais: dar `analyze-occurrences` a um funcionário de
 * confiança não teria efeito nenhum aqui.
 */
class UpdateOccurrenceStatusAction
{
    public static function execute(
        Occurrence $occurrence,
        User $author,
        OccurrenceStatus $status,
        ?string $message = null,
    ): Occurrence {
        self::checkTransition($occurrence, $author, $status);

        $previousStatus = $occurrence->status;

        $occurrence->update(['status' => $status]);

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::StatusChanged,
            $previousStatus,
            $status,
            $message,
        );

        $description = "Ocorrência {$occurrence->id} {$occurrence->title} passou de {$previousStatus->label()} para {$status->label()}.";
        CreateLogAction::execute(TypeLogEnum::OCCURRENCE->value, $description, [
            'de' => $previousStatus->value,
            'para' => $status->value,
            'observacao' => $message,
        ]);

        return $occurrence->refresh();
    }

    /**
     * Primeiro o fluxo, depois a pessoa.
     *
     * As duas recusas são de natureza diferente e saem com códigos diferentes:
     * fora do fluxo é 409 — ninguém faz essa transição, nem o administrador;
     * dentro do fluxo mas sem autorização é 403 — a transição existe, só não
     * para quem pediu. Antes as duas saíam iguais, e quem estava do outro lado
     * não tinha como saber se devia pedir a outra pessoa ou desistir.
     */
    private static function checkTransition(Occurrence $occurrence, User $author, OccurrenceStatus $status): void
    {
        $current = $occurrence->status;

        if ($current === $status && ! $current->isFinal()) {
            throw BusinessRuleException::conflict("O chamado já está {$status->label()}.");
        }

        $current->assertCanTransitionTo($status);

        if ($status === OccurrenceStatus::Completed) {
            throw BusinessRuleException::unprocessable(
                'O chamado é concluído pela finalização, que exige a conclusão do atendimento e se o problema foi resolvido.'
            );
        }

        if ($status === OccurrenceStatus::Assigned) {
            throw BusinessRuleException::unprocessable(
                'O chamado passa a atribuído quando se escolhe o funcionário responsável.'
            );
        }

        self::checkEmployeeOwnsIt($occurrence, $author);

        if (! in_array($status, self::allowedTransitions($occurrence, $author), true)) {
            throw BusinessRuleException::forbidden(
                "Você não tem autorização para levar o chamado de {$current->label()} para {$status->label()}."
            );
        }
    }

    /**
     * RN15 — quem só tem o caso de uso do funcionário age apenas no que é seu.
     *
     * A recusa vem antes da checagem de transição para a resposta dizer o
     * motivo real: "não é seu" explica, "transição não permitida" confunde.
     */
    private static function checkEmployeeOwnsIt(Occurrence $occurrence, User $author): void
    {
        $soAtende = ! $author->hasUseCase(UseCase::AnalyzeOccurrences)
            && $author->hasUseCase(UseCase::UpdateOccurrenceProgress);

        if ($soAtende && $occurrence->assigned_employee_id !== $author->id) {
            throw BusinessRuleException::forbidden('Você só pode atender os chamados atribuídos a você.');
        }
    }

    /**
     * O que cada caso de uso permite a partir do status atual.
     *
     * É a união do que a pessoa tem: quem acumula os dois casos de uso por
     * concessão individual soma as duas listas.
     *
     * @return list<OccurrenceStatus>
     */
    private static function allowedTransitions(Occurrence $occurrence, User $author): array
    {
        $current = $occurrence->status;
        $allowed = [];

        if ($author->hasUseCase(UseCase::AnalyzeOccurrences)) {
            $allowed = match ($current) {
                OccurrenceStatus::Open => [OccurrenceStatus::Analysis, OccurrenceStatus::Cancelled],
                // Cancelar só enquanto o atendimento não começou: depois disso
                // o desfecho é dado pela finalização, como resolvido ou não.
                OccurrenceStatus::Analysis, OccurrenceStatus::Assigned => [OccurrenceStatus::Cancelled],
                default => [],
            };
        }

        $atribuidoAoAutor = $occurrence->assigned_employee_id === $author->id;

        if ($author->hasUseCase(UseCase::UpdateOccurrenceProgress) && $atribuidoAoAutor && $current === OccurrenceStatus::Assigned) {
            $allowed[] = OccurrenceStatus::InProgress;
        }

        return $allowed;
    }
}
