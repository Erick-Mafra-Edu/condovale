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
 * RF04 — atualização do atendimento: mudar o status, registrar o andamento,
 * ou os dois numa vez.
 *
 * O que faltava era o andamento sem mudança de status. O funcionário que
 * encomendou a peça precisa avisar que ela chega na quinta, e o chamado
 * continua Em andamento — até aqui não havia onde escrever isso.
 *
 * A mudança de status não tem regra própria aqui: vai para a
 * UpdateOccurrenceStatusAction, a mesma do PATCH /status. São duas portas e
 * uma regra só, para as duas nunca discordarem sobre quem pode levar o
 * chamado de onde para onde.
 *
 * O morador do chamado não é tocado: nada aqui altera `resident_id`, e o
 * model recusa a troca mesmo que alguém tente por outro caminho.
 */
class UpdateOccurrenceAttendanceAction
{
    public static function execute(Occurrence $occurrence, User $author, ?OccurrenceStatus $status, ?string $message): Occurrence
    {
        $message = trim((string) $message) ?: null;

        if ($status === null && $message === null) {
            throw BusinessRuleException::unprocessable('Informe o novo status ou descreva a atualização do atendimento.');
        }

        CheckOccurrenceAccessAction::execute($occurrence, $author);

        // Status igual ao atual, com anotação, é anotação. A tela costuma
        // mandar o status corrente junto com o texto, e recusar isso com
        // "o chamado já está Em andamento" puniria quem só quis escrever.
        if ($status !== null && ! ($status === $occurrence->status && $message !== null)) {
            return UpdateOccurrenceStatusAction::execute($occurrence, $author, $status, $message);
        }

        self::checkCanNote($occurrence, $author);

        CreateOccurrenceHistoryAction::execute(
            $occurrence,
            $author,
            OccurrenceHistoryType::Comment,
            null,
            null,
            $message,
        );

        $description = "Andamento registrado na ocorrência {$occurrence->id} {$occurrence->title} por {$author->name}.";
        CreateLogAction::execute(TypeLogEnum::OCCURRENCE->value, $description, ['andamento' => $message]);

        return $occurrence->refresh();
    }

    /**
     * Quem registra andamento: administração e síndico em qualquer chamado,
     * o funcionário só no que é dele. Chamado encerrado não recebe mais nada
     * — o que estava em discussão já foi decidido.
     */
    private static function checkCanNote(Occurrence $occurrence, User $author): void
    {
        if ($occurrence->status->isFinal()) {
            throw BusinessRuleException::conflict(
                "Este chamado já está {$occurrence->status->label()} e não recebe mais atualizações."
            );
        }

        if ($author->hasUseCase(UseCase::AnalyzeOccurrences)) {
            return;
        }

        $atende = $author->hasUseCase(UseCase::UpdateOccurrenceProgress)
            && $occurrence->assigned_employee_id === $author->id;

        if (! $atende) {
            throw BusinessRuleException::forbidden('Você só pode atualizar o atendimento dos chamados atribuídos a você.');
        }
    }
}
