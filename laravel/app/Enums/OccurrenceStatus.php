<?php

namespace App\Enums;

use App\Exceptions\BusinessRuleException;

/**
 * A máquina de estados da ocorrência.
 *
 * Correspondência com a especificação: ABERTA = open, EM_ANALISE = analysis,
 * ENCAMINHADA = assigned, EM_ATENDIMENTO = in_progress, FINALIZADA = completed.
 * `cancelled` não estava na especificação: existe para o chamado duplicado ou
 * improcedente, que não deve ser contado como atendido.
 *
 * O caminho principal é o da especificação e do fluxo documentado pelo time:
 *
 *     open → analysis → assigned → in_progress → completed
 *
 * Fora dele há três desvios, todos decididos nos cards:
 *
 * - **Cancelar** enquanto o atendimento não começou. Depois de iniciado, o
 *   fim é a finalização, como resolvido ou não: cancelar apagaria o fato de
 *   que alguém trabalhou no chamado.
 * - **Finalizar de qualquer etapa aberta.** Síndico e administração dão baixa
 *   no que ninguém vai atender — uma queixa de barulho resolvida numa conversa
 *   não passa por "em andamento".
 * - **Repassar um atendimento iniciado** (in_progress → assigned). O
 *   encanador que descobre que o problema é elétrico precisa passar o chamado
 *   adiante; sem esta volta, a única saída seria encerrá-lo como não
 *   resolvido e abrir outro. Só acontece pelo encaminhamento, que exige
 *   justificativa — o PATCH de status recusa "atribuído".
 *
 * Este enum diz **o que** o fluxo permite, independente de quem pede. Quem
 * pode fazer cada transição é decidido pelos casos de uso, nas Actions. E
 * quem garante que nenhum caminho pule o fluxo é o model Occurrence, que
 * confere toda gravação que mude o status.
 */
enum OccurrenceStatus: string
{
    case Open = 'open';
    case Analysis = 'analysis';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Para onde o chamado pode ir a partir deste status.
     *
     * Note o que **não** está aqui: atribuir sem analisar (open → assigned),
     * voltar à análise, cancelar um atendimento iniciado e reabrir um chamado
     * encerrado. Reabrir é decisão pendente da especificação; enquanto não for
     * tomada, encerrado é encerrado.
     *
     * @return list<self>
     */
    public function nextStates(): array
    {
        return match ($this) {
            self::Open => [self::Analysis, self::Cancelled, self::Completed],
            self::Analysis => [self::Assigned, self::Cancelled, self::Completed],
            self::Assigned => [self::InProgress, self::Cancelled, self::Completed],
            self::InProgress => [self::Assigned, self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextStates(), true);
    }

    /**
     * Recusa a transição fora do fluxo, dizendo para onde o chamado pode ir.
     *
     * A mensagem lista os próximos estados porque "transição inválida" sozinho
     * não ajuda quem está do outro lado a saber o que fazer.
     */
    public function assertCanTransitionTo(self $next): void
    {
        if ($this->canTransitionTo($next)) {
            return;
        }

        if ($this->isFinal()) {
            throw BusinessRuleException::conflict(
                "Este chamado já está {$this->label()} e não muda mais de status."
            );
        }

        $destinos = array_map(fn (self $status) => $status->label(), $this->nextStates());
        $ultimo = array_pop($destinos);
        $lista = $destinos ? implode(', ', $destinos)." ou {$ultimo}" : $ultimo;

        throw BusinessRuleException::conflict(
            "O chamado não pode passar de {$this->label()} para {$next->label()}: de {$this->label()} ele segue para {$lista}."
        );
    }

    /**
     * Encerrado é o estado sem saída. Derivado do grafo, e não de uma lista à
     * parte, para as duas coisas nunca discordarem.
     */
    public function isFinal(): bool
    {
        return $this->nextStates() === [];
    }

    /**
     * Rótulo para as mensagens que chegam ao usuário: "Não é possível mudar o
     * chamado de Aberto para Em andamento" se lê; "open para in_progress", não.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::Analysis => 'Em análise',
            self::Assigned => 'Atribuído',
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }
}
