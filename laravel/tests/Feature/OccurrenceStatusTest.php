<?php

namespace Tests\Feature;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\TypeLogEnum;
use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RN06 / RN15 — só quem tem autorização muda o status do chamado.
 *
 * A administração analisa e cancela enquanto o atendimento não começou; o
 * funcionário atribuído inicia o atendimento. Concluir e atribuir têm portas
 * próprias e são recusados aqui.
 */
class OccurrenceStatusTest extends TestCase
{
    use RefreshDatabase;

    private function chamado(array $attributes = []): Occurrence
    {
        return Occurrence::factory()->create(array_merge(['title' => 'Vazamento no corredor'], $attributes));
    }

    private function mudarStatus(User $autor, Occurrence $chamado, string $status, array $extra = [])
    {
        return $this->actingAs($autor)->patchJson(
            "/api/occurrences/{$chamado->id}/status",
            array_merge(['status' => $status], $extra)
        );
    }

    public function test_a_administracao_coloca_o_chamado_em_analise(): void
    {
        $chamado = $this->chamado();

        $this->mudarStatus(User::factory()->admin()->create(), $chamado, 'analysis')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'analysis')
            ->assertJsonPath('message', "Ocorrência {$chamado->id} agora está Em análise.");
    }

    public function test_a_administracao_cancela_enquanto_o_atendimento_nao_comecou(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([OccurrenceStatus::Open, OccurrenceStatus::Analysis, OccurrenceStatus::Assigned] as $status) {
            $chamado = $this->chamado(['status' => $status]);

            $this->mudarStatus($admin, $chamado, 'cancelled')
                ->assertStatus(201)
                ->assertJsonPath('data.status', 'cancelled');
        }
    }

    public function test_depois_de_iniciado_o_desfecho_e_pela_finalizacao(): void
    {
        // Cancelar um atendimento em andamento apagaria o fato de que alguém
        // trabalhou nele. Depois de iniciado, o fim é a finalização, como
        // resolvido ou não.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create();

        $this->mudarStatus(User::factory()->admin()->create(), $chamado, 'cancelled')
            ->assertStatus(409)
            ->assertJsonPath('message', 'O chamado não pode passar de Em andamento para Cancelado: de Em andamento ele segue para Concluído.');
    }

    public function test_o_funcionario_atribuido_inicia_o_atendimento(): void
    {
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->assignedTo($funcionario)->create();

        $this->mudarStatus($funcionario, $chamado, 'in_progress')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_rn15_outro_funcionario_nao_mexe_no_chamado(): void
    {
        $responsavel = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->assignedTo($responsavel)->create();

        $this->mudarStatus(User::factory()->employee()->create(), $chamado, 'in_progress')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você só pode atender os chamados atribuídos a você.');
    }

    public function test_o_funcionario_nao_cancela_o_proprio_chamado(): void
    {
        // A transição existe no fluxo — a administração cancela chamado
        // atribuído —, só não para o funcionário. Por isso 403, e não 409.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->assignedTo($funcionario)->create();

        $this->mudarStatus($funcionario, $chamado, 'cancelled')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem autorização para levar o chamado de Atribuído para Cancelado.');
    }

    public function test_concluir_nao_passa_pelo_patch(): void
    {
        // Era uma porta para encerrar sem dizer como terminou: o funcionário
        // podia mandar `completed` aqui e pular a conclusão e o desfecho.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create();

        $this->mudarStatus($funcionario, $chamado, 'completed')
            ->assertStatus(422)
            ->assertJsonPath('message', 'O chamado é concluído pela finalização, que exige a conclusão do atendimento e se o problema foi resolvido.');

        $this->assertSame(OccurrenceStatus::InProgress, $chamado->fresh()->status);
    }

    public function test_atribuir_nao_passa_pelo_patch(): void
    {
        // "Atribuído" sem ninguém atribuído é um chamado que parece estar
        // sendo atendido e que ninguém atende.
        $emAnalise = $this->chamado(['status' => OccurrenceStatus::Analysis]);

        $this->mudarStatus(User::factory()->admin()->create(), $emAnalise, 'assigned')
            ->assertStatus(422)
            ->assertJsonPath('message', 'O chamado passa a atribuído quando se escolhe o funcionário responsável.');
    }

    public function test_fora_do_fluxo_e_409_para_qualquer_um(): void
    {
        // Pular a análise não é questão de quem pede: nem o administrador
        // faz. A resposta lista para onde o chamado pode ir.
        $this->mudarStatus(User::factory()->admin()->create(), $this->chamado(), 'assigned')
            ->assertStatus(409)
            ->assertJsonPath('message', 'O chamado não pode passar de Aberto para Atribuído: de Aberto ele segue para Em análise, Cancelado ou Concluído.');
    }

    public function test_chamado_encerrado_nao_muda_mais(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([OccurrenceStatus::Completed, OccurrenceStatus::Cancelled] as $final) {
            $chamado = $this->chamado(['status' => $final]);

            $this->mudarStatus($admin, $chamado, 'analysis')
                ->assertStatus(409)
                ->assertJsonPath('message', "Este chamado já está {$final->label()} e não muda mais de status.");
        }
    }

    public function test_recusa_mudar_para_o_mesmo_status(): void
    {
        $this->mudarStatus(User::factory()->admin()->create(), $this->chamado(), 'open')
            ->assertStatus(409)
            ->assertJsonPath('message', 'O chamado já está Aberto.');
    }

    public function test_registra_a_transicao_no_historico(): void
    {
        $admin = User::factory()->admin()->create();
        $chamado = $this->chamado();

        $this->mudarStatus($admin, $chamado, 'analysis', ['message' => 'Verificar com a portaria.']);

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'user_id' => $admin->id,
            'type' => OccurrenceHistoryType::StatusChanged->value,
            'previous_status' => 'open',
            'new_status' => 'analysis',
            'message' => 'Verificar com a portaria.',
        ]);
    }

    public function test_registra_a_transicao_na_auditoria(): void
    {
        $chamado = $this->chamado();

        $this->mudarStatus(User::factory()->admin()->create(), $chamado, 'analysis');

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE->value,
            'description' => "Ocorrência {$chamado->id} Vazamento no corredor passou de Aberto para Em análise.",
        ]);
    }

    public function test_a_regra_segue_o_caso_de_uso_e_nao_o_papel(): void
    {
        // A versão anterior decidia por `users.role` e ignorava concessões
        // individuais: dar `analyze-occurrences` a um funcionário não tinha
        // efeito. Agora tem.
        $funcionario = User::factory()->employee()->create();
        $funcionario->givePermissionTo('analyze-occurrences');

        $this->mudarStatus($funcionario, $this->chamado(), 'analysis')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'analysis');
    }

    public function test_o_sindico_encerra_mas_nao_movimenta_o_status(): void
    {
        // O síndico dá baixa pela finalização; as etapas intermediárias são
        // da administração e do funcionário.
        $this->mudarStatus(User::factory()->syndic()->create(), $this->chamado(), 'analysis')
            ->assertStatus(403);
    }

    public function test_o_morador_nao_muda_status(): void
    {
        $chamado = $this->chamado();

        $this->mudarStatus($chamado->resident, $chamado, 'cancelled')->assertStatus(403);
    }

    public function test_chamado_inexistente_responde_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson('/api/occurrences/999/status', ['status' => 'analysis'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Ocorrência 999 não encontrada.');
    }

    public function test_recusa_status_que_nao_existe(): void
    {
        $this->mudarStatus(User::factory()->admin()->create(), $this->chamado(), 'arquivado')
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Status de ocorrência inválido.');
    }

    public function test_exige_autenticacao(): void
    {
        $this->patchJson('/api/occurrences/1/status', ['status' => 'analysis'])->assertStatus(401);
    }
}
