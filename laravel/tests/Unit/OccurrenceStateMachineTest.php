<?php

namespace Tests\Unit;

use App\Enums\OccurrenceStatus;
use App\Exceptions\BusinessRuleException;
use PHPUnit\Framework\TestCase;

/**
 * A máquina de estados da ocorrência, par a par.
 *
 * O teste declara as transições permitidas por extenso e confere os 36 pares
 * possíveis — inclusive os proibidos. Assim, qualquer mudança no fluxo vira
 * uma alteração deliberada aqui, e não um efeito colateral de quem mexeu no
 * enum por outro motivo.
 */
class OccurrenceStateMachineTest extends TestCase
{
    /**
     * As únicas transições que o fluxo admite.
     *
     * O caminho da especificação é a coluna da esquerda de cada estado; os
     * outros dois destinos são os atalhos decididos nos cards anteriores —
     * cancelar antes do atendimento começar e dar baixa de qualquer etapa
     * aberta.
     *
     * @var array<string, list<string>>
     */
    private const PERMITIDAS = [
        'open' => ['analysis', 'cancelled', 'completed'],
        'analysis' => ['assigned', 'cancelled', 'completed'],
        'assigned' => ['in_progress', 'cancelled', 'completed'],
        'in_progress' => ['assigned', 'completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function test_cada_par_de_estados_segue_a_tabela(): void
    {
        foreach (OccurrenceStatus::cases() as $de) {
            foreach (OccurrenceStatus::cases() as $para) {
                $esperado = in_array($para->value, self::PERMITIDAS[$de->value], true);

                $this->assertSame(
                    $esperado,
                    $de->canTransitionTo($para),
                    "{$de->value} → {$para->value} deveria ser ".($esperado ? 'permitida' : 'recusada').'.'
                );
            }
        }
    }

    public function test_o_caminho_da_especificacao_e_percorrivel(): void
    {
        // ABERTA → EM_ANALISE → ENCAMINHADA → EM_ATENDIMENTO → FINALIZADA.
        $caminho = [
            OccurrenceStatus::Open,
            OccurrenceStatus::Analysis,
            OccurrenceStatus::Assigned,
            OccurrenceStatus::InProgress,
            OccurrenceStatus::Completed,
        ];

        for ($i = 0; $i < count($caminho) - 1; $i++) {
            $this->assertTrue(
                $caminho[$i]->canTransitionTo($caminho[$i + 1]),
                "O caminho principal quebra em {$caminho[$i]->value} → {$caminho[$i + 1]->value}."
            );
        }
    }

    public function test_nao_se_pula_a_analise(): void
    {
        // Encaminhar a um funcionário pressupõe que alguém leu o chamado.
        $this->assertFalse(OccurrenceStatus::Open->canTransitionTo(OccurrenceStatus::Assigned));
        $this->assertFalse(OccurrenceStatus::Open->canTransitionTo(OccurrenceStatus::InProgress));
    }

    public function test_nao_se_volta_a_analise_nem_a_abertura(): void
    {
        $this->assertFalse(OccurrenceStatus::Analysis->canTransitionTo(OccurrenceStatus::Open));
        $this->assertFalse(OccurrenceStatus::Assigned->canTransitionTo(OccurrenceStatus::Analysis));
        $this->assertFalse(OccurrenceStatus::InProgress->canTransitionTo(OccurrenceStatus::Analysis));
    }

    public function test_atendimento_iniciado_pode_ser_repassado(): void
    {
        // O encanador que descobre que o problema é elétrico. Sem esta volta,
        // a única saída seria encerrar como não resolvido e abrir outro.
        $this->assertTrue(OccurrenceStatus::InProgress->canTransitionTo(OccurrenceStatus::Assigned));
    }

    public function test_atendimento_iniciado_nao_e_cancelado(): void
    {
        // Cancelar apagaria o fato de que alguém trabalhou no chamado. Depois
        // de iniciado, o fim é a finalização, como resolvido ou não.
        $this->assertFalse(OccurrenceStatus::InProgress->canTransitionTo(OccurrenceStatus::Cancelled));
    }

    public function test_encerrado_nao_reabre(): void
    {
        // Reabrir é decisão pendente da especificação; até lá, encerrado é
        // encerrado.
        foreach ([OccurrenceStatus::Completed, OccurrenceStatus::Cancelled] as $final) {
            $this->assertTrue($final->isFinal());
            $this->assertSame([], $final->nextStates());
        }
    }

    public function test_o_estado_final_e_derivado_do_grafo(): void
    {
        // isFinal() e nextStates() não podem discordar: um estado com saída
        // marcado como final travaria chamados que ainda deveriam andar.
        foreach (OccurrenceStatus::cases() as $status) {
            $this->assertSame($status->nextStates() === [], $status->isFinal(), $status->value);
        }
    }

    public function test_nenhum_estado_transiciona_para_si_mesmo(): void
    {
        foreach (OccurrenceStatus::cases() as $status) {
            $this->assertFalse($status->canTransitionTo($status), $status->value);
        }
    }

    public function test_a_recusa_diz_para_onde_o_chamado_pode_ir(): void
    {
        try {
            OccurrenceStatus::Open->assertCanTransitionTo(OccurrenceStatus::InProgress);
            $this->fail('A transição deveria ter sido recusada.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame(
                'O chamado não pode passar de Aberto para Em andamento: de Aberto ele segue para Em análise, Cancelado ou Concluído.',
                $e->getMessage()
            );
        }
    }

    public function test_a_recusa_com_dois_destinos_usa_ou(): void
    {
        try {
            OccurrenceStatus::InProgress->assertCanTransitionTo(OccurrenceStatus::Open);
            $this->fail('A transição deveria ter sido recusada.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(
                'O chamado não pode passar de Em andamento para Aberto: de Em andamento ele segue para Atribuído ou Concluído.',
                $e->getMessage()
            );
        }
    }

    public function test_a_recusa_de_estado_final_diz_que_ele_nao_muda_mais(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Este chamado já está Concluído e não muda mais de status.');

        OccurrenceStatus::Completed->assertCanTransitionTo(OccurrenceStatus::Open);
    }
}
