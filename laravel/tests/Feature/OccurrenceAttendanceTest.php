<?php

namespace Tests\Feature;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\TypeLogEnum;
use App\Exceptions\BusinessRuleException;
use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF04 — atualização do atendimento, preservando o morador que abriu o chamado.
 *
 * Status, anotação de andamento, ou os dois. Administração e síndico em
 * qualquer chamado; o funcionário só no que é dele.
 */
class OccurrenceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function atualizar(User $autor, Occurrence $chamado, array $corpo)
    {
        return $this->actingAs($autor)->patchJson("/api/occurrences/{$chamado->id}", $corpo);
    }

    public function test_o_funcionario_registra_andamento_sem_mudar_o_status(): void
    {
        // O que faltava: avisar que a peça chega na quinta, com o chamado
        // continuando Em andamento.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create(['title' => 'Portão travando']);

        $this->atualizar($funcionario, $chamado, ['message' => 'Peça encomendada; chega na quinta.'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('message', "Atendimento da ocorrência {$chamado->id} atualizado.");

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'user_id' => $funcionario->id,
            'type' => OccurrenceHistoryType::Comment->value,
            'message' => 'Peça encomendada; chega na quinta.',
        ]);
    }

    public function test_status_e_anotacao_na_mesma_atualizacao(): void
    {
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->assignedTo($funcionario)->create();

        $this->atualizar($funcionario, $chamado, ['status' => 'in_progress', 'message' => 'Cheguei ao local.'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'type' => OccurrenceHistoryType::StatusChanged->value,
            'previous_status' => 'assigned',
            'new_status' => 'in_progress',
            'message' => 'Cheguei ao local.',
        ]);
    }

    public function test_status_igual_ao_atual_com_anotacao_e_anotacao(): void
    {
        // A tela costuma mandar o status corrente junto com o texto.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create();

        $this->atualizar($funcionario, $chamado, ['status' => 'in_progress', 'message' => 'Ainda aguardando a peça.'])
            ->assertStatus(201);

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'type' => OccurrenceHistoryType::Comment->value,
            'message' => 'Ainda aguardando a peça.',
        ]);
    }

    public function test_status_igual_sem_anotacao_e_recusado(): void
    {
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create();

        $this->atualizar($funcionario, $chamado, ['status' => 'in_progress'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'O chamado já está Em andamento.');
    }

    public function test_a_mudanca_de_status_segue_a_mesma_regra_do_patch_de_status(): void
    {
        // Concluir continua sendo pela finalização, nesta porta também.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create();

        $this->atualizar($funcionario, $chamado, ['status' => 'completed', 'message' => 'Pronto.'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O chamado é concluído pela finalização, que exige a conclusão do atendimento e se o problema foi resolvido.');
    }

    public function test_administracao_e_sindico_registram_andamento_em_qualquer_chamado(): void
    {
        $chamado = Occurrence::factory()->inProgressWith(User::factory()->employee()->create())->create();

        foreach ([User::factory()->admin()->create(), User::factory()->syndic()->create()] as $gestor) {
            $this->atualizar($gestor, $chamado, ['message' => 'Morador avisado por telefone.'])
                ->assertStatus(201);
        }
    }

    public function test_o_funcionario_nao_atualiza_chamado_de_colega(): void
    {
        $chamado = Occurrence::factory()->inProgressWith(User::factory()->employee()->create())->create();

        $this->atualizar(User::factory()->employee()->create(), $chamado, ['message' => 'intruso'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem acesso a este chamado.');
    }

    public function test_chamado_encerrado_nao_recebe_atualizacao(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([OccurrenceStatus::Completed, OccurrenceStatus::Cancelled] as $final) {
            $chamado = Occurrence::factory()->create(['status' => $final]);

            $this->atualizar($admin, $chamado, ['message' => 'tarde demais'])
                ->assertStatus(409)
                ->assertJsonPath('message', "Este chamado já está {$final->label()} e não recebe mais atualizações.");
        }
    }

    public function test_exige_status_ou_anotacao(): void
    {
        $chamado = Occurrence::factory()->create();

        $this->atualizar(User::factory()->admin()->create(), $chamado, [])
            ->assertStatus(422)
            ->assertJsonPath('errors.message.0', 'Descreva a atualização do atendimento ou informe o novo status.');
    }

    public function test_recusa_anotacao_longa_demais(): void
    {
        $chamado = Occurrence::factory()->create();

        $this->atualizar(User::factory()->admin()->create(), $chamado, ['message' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonPath('errors.message.0', 'A atualização deve ter no máximo 2000 caracteres.');
    }

    public function test_o_morador_do_chamado_nao_muda_pela_atualizacao(): void
    {
        // "Preservando a associação ao morador que realizou a abertura": o
        // campo nem é aceito, e o chamado continua de quem o abriu.
        $chamado = Occurrence::factory()->create();
        $dono = $chamado->resident_id;
        $outro = User::factory()->resident()->create();

        $this->atualizar(User::factory()->admin()->create(), $chamado, [
            'message' => 'Tentando trocar o morador.',
            'resident_id' => $outro->id,
        ])->assertStatus(201);

        $this->assertSame($dono, $chamado->fresh()->resident_id);
    }

    public function test_o_model_recusa_trocar_o_morador_por_qualquer_caminho(): void
    {
        // A trava fica no model, como a máquina de estados: vale mesmo para
        // código que não passe pela Action.
        $chamado = Occurrence::factory()->create();
        $dono = $chamado->resident_id;

        try {
            $chamado->update(['resident_id' => User::factory()->resident()->create()->id]);
            $this->fail('O model deveria ter recusado a troca do morador.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('O chamado continua sendo do morador que o abriu: o morador não pode ser trocado.', $e->getMessage());
        }

        $this->assertSame($dono, $chamado->fresh()->resident_id);
    }

    public function test_registra_o_andamento_na_auditoria(): void
    {
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create(['title' => 'Portão travando']);

        $this->atualizar($funcionario, $chamado, ['message' => 'Motor revisado.']);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE->value,
            'description' => "Andamento registrado na ocorrência {$chamado->id} Portão travando por Pedro Manutenção.",
        ]);
    }

    public function test_o_morador_nao_atualiza_o_atendimento(): void
    {
        $chamado = Occurrence::factory()->create();

        $this->atualizar($chamado->resident, $chamado, ['message' => 'Piorou.'])
            ->assertStatus(403);
    }

    public function test_chamado_inexistente_responde_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson('/api/occurrences/999', ['message' => 'x'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Ocorrência 999 não encontrada.');
    }

    public function test_exige_autenticacao(): void
    {
        $this->patchJson('/api/occurrences/1', ['message' => 'x'])->assertStatus(401);
    }
}
