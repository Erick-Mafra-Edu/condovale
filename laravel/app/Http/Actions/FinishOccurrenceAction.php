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
 * RF04 / RN06 — dar baixa no chamado, dizendo como terminou.
 *
 * Quem encerra depende de quem é a pessoa e de que tipo é o chamado:
 *
 * - **Síndico e administração** (`close-any-occurrence`) encerram qualquer
 *   chamado, de qualquer tipo, em qualquer ponto do ciclo. Quem responde pelo
 *   condomínio precisa poder fechar o que ninguém vai atender — uma queixa de
 *   barulho resolvida numa conversa não passa por "em andamento".
 * - **O funcionário** (`finish-occurrence`) encerra só o atendimento que é
 *   dele (RN15), depois de iniciá-lo, e só quando o tipo permite
 *   (`occurrence_types.employee_can_close`). Um vazamento ele conserta e dá
 *   baixa; uma briga por vaga não se resolve com ferramenta, e encerrá-la é
 *   decisão de quem responde pelo condomínio.
 *
 * Como o resto da autorização do projeto, a regra olha o caso de uso e não a
 * coluna `users.role`: conceder `close-any-occurrence` a um funcionário de
 * confiança o libera de verdade.
 *
 * Encerrar não é resolver. O chamado vai para Concluído nos dois casos, e
 * `resolved` diz se o problema acabou — é essa a diferença que o relatório do
 * síndico precisa mostrar.
 */
class FinishOccurrenceAction
{
    public static function execute(Occurrence $occurrence, User $author, bool $resolved, string $conclusion): Occurrence
    {
        if ($occurrence->status->isFinal()) {
            throw BusinessRuleException::conflict(
                "Este chamado já está {$occurrence->status->label()}."
            );
        }

        if (! $author->hasUseCase(UseCase::CloseAnyOccurrence)) {
            self::checkEmployeeMayClose($occurrence, $author);
        }

        $previousStatus = $occurrence->status;

        $occurrence->update([
            'status' => OccurrenceStatus::Completed,
            'completed_at' => now(),
            'resolved' => $resolved,
            'conclusion' => $conclusion,
        ]);

        $desfecho = $resolved ? 'Resolvido' : 'Não resolvido';

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::Completed,
            $previousStatus,
            OccurrenceStatus::Completed,
            "{$desfecho}. {$conclusion}",
        );

        $como = $resolved ? 'resolvida' : 'não resolvida';
        $description = "Ocorrência {$occurrence->id} {$occurrence->title} encerrada como {$como} por {$author->name}.";
        CreateLogAction::execute(TypeLogEnum::OCCURRENCE->value, $description, [
            'de' => $previousStatus->value,
            'resolvido' => $resolved,
            'conclusao' => $conclusion,
        ]);

        return $occurrence->refresh();
    }

    /**
     * As três condições do funcionário, na ordem que melhor explica a recusa.
     *
     * Primeiro "não é seu", porque nada mais importa se não for. Depois o tipo,
     * porque se ele não permite, iniciar o atendimento não adiantaria — dizer
     * "inicie antes" mandaria a pessoa dar um passo inútil. Só então o status.
     */
    private static function checkEmployeeMayClose(Occurrence $occurrence, User $author): void
    {
        if (! $author->hasUseCase(UseCase::FinishOccurrence)) {
            throw BusinessRuleException::forbidden('Você não tem autorização para encerrar chamados.');
        }

        if ($occurrence->assigned_employee_id !== $author->id) {
            throw BusinessRuleException::forbidden('Você só pode encerrar os chamados atribuídos a você.');
        }

        $tipo = $occurrence->occurrenceType;

        if (! $tipo?->employee_can_close) {
            $nome = $tipo ? "do tipo {$tipo->name}" : 'sem tipo';

            throw BusinessRuleException::forbidden(
                "Chamados {$nome} são encerrados pelo síndico ou pela administração."
            );
        }

        if ($occurrence->status !== OccurrenceStatus::InProgress) {
            throw BusinessRuleException::conflict(
                "Inicie o atendimento antes de encerrar o chamado: ele ainda está {$occurrence->status->label()}."
            );
        }
    }
}
