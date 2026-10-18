<?php

namespace Tests\Feature;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\TypeLogEnum;
use App\Http\Actions\ScopeOccurrencesAction;
use App\Models\Occurrence;
use App\Models\OccurrenceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF04 / RN06 — dar baixa no chamado, dizendo como terminou.
 *
 * Síndico e administração encerram qualquer chamado. O funcionário encerra só
 * o que é dele, depois de iniciar, e só quando o tipo permite. Encerrar não é
 * resolver: o chamado vai para Concluído nos dois casos, e `resolved` diz se o
 * problema acabou.
 */
class OccurrenceFinishTest extends TestCase
{
    use RefreshDatabase;

    private function tipo(bool $funcionarioEncerra, string $nome = 'Manutenção predial'): OccurrenceType
    {
        return OccurrenceType::factory()->create([
            'name' => $nome,
            'employee_can_close' => $funcionarioEncerra,
        ]);
    }

    private function encerrar(User $autor, Occurrence $chamado, array $corpo = [])
    {
        return $this->actingAs($autor)->postJson(
            "/api/occurrences/{$chamado->id}/finish",
            array_merge([
                'resolved' => true,
                'conclusion' => 'Registro trocado e vazamento estancado.',
            ], $corpo)
        );
    }

    public function test_o_sindico_da_baixa_como_resolvido(): void
    {
        $chamado = Occurrence::factory()->create(['title' => 'Vazamento no corredor']);

        $this->encerrar(User::factory()->syndic()->create(), $chamado)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.resolved', true)
            ->assertJsonPath('data.conclusion', 'Registro trocado e vazamento estancado.')
            ->assertJsonPath('message', "Ocorrência {$chamado->id} resolvida.");

        $this->assertNotNull($chamado->fresh()->completed_at);
    }

    public function test_encerrar_sem_resolver_e_um_desfecho_valido(): void
    {
        // A peça não existe, o problema é da concessionária: o chamado sai da
        // fila, mas o relatório precisa saber que ele não foi resolvido.
        $chamado = Occurrence::factory()->create();

        $this->encerrar(User::factory()->syndic()->create(), $chamado, [
            'resolved' => false,
            'conclusion' => 'Falha na rede da concessionária; aberto protocolo junto à Celesc.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.resolved', false)
            ->assertJsonPath('message', "Ocorrência {$chamado->id} encerrada sem solução.");
    }

    public function test_o_sindico_encerra_qualquer_tipo_em_qualquer_etapa(): void
    {
        // Uma queixa de barulho resolvida numa conversa nunca passa por "em
        // andamento". Quem responde pelo condomínio precisa poder fechá-la.
        $sindico = User::factory()->syndic()->create();
        $conflito = $this->tipo(false, 'Barulho e sossego');

        foreach ([OccurrenceStatus::Open, OccurrenceStatus::Analysis, OccurrenceStatus::Assigned] as $status) {
            $chamado = Occurrence::factory()->create(['status' => $status, 'occurrence_type_id' => $conflito->id]);

            $this->encerrar($sindico, $chamado)->assertStatus(201);
        }
    }

    public function test_a_administracao_tambem_da_baixa(): void
    {
        $this->encerrar(User::factory()->admin()->create(), Occurrence::factory()->create())
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_o_funcionario_encerra_o_proprio_atendimento_quando_o_tipo_permite(): void
    {
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create([
            'occurrence_type_id' => $this->tipo(true)->id,
        ]);

        $this->encerrar($funcionario, $chamado)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.resolved', true);
    }

    public function test_o_funcionario_nao_encerra_tipo_reservado_a_administracao(): void
    {
        // Briga por vaga não se resolve com ferramenta: encerrar é decisão de
        // quem responde pelo condomínio, mesmo com o atendimento em andamento.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create([
            'occurrence_type_id' => $this->tipo(false, 'Estacionamento')->id,
        ]);

        $this->encerrar($funcionario, $chamado)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Chamados do tipo Estacionamento são encerrados pelo síndico ou pela administração.');

        $this->assertSame(OccurrenceStatus::InProgress, $chamado->fresh()->status);
    }

    public function test_rn15_o_funcionario_nao_encerra_chamado_de_outro(): void
    {
        $responsavel = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($responsavel)->create([
            'occurrence_type_id' => $this->tipo(true)->id,
        ]);

        $this->encerrar(User::factory()->employee()->create(), $chamado)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você só pode encerrar os chamados atribuídos a você.');
    }

    public function test_o_funcionario_inicia_o_atendimento_antes_de_encerrar(): void
    {
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->assignedTo($funcionario)->create([
            'occurrence_type_id' => $this->tipo(true)->id,
        ]);

        $this->encerrar($funcionario, $chamado)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Inicie o atendimento antes de encerrar o chamado: ele ainda está Atribuído.');
    }

    public function test_a_recusa_diz_o_motivo_que_importa(): void
    {
        // Chamado de outro e de tipo reservado: a resposta é "não é seu". Se
        // fosse do funcionário mas o tipo não permitisse, a resposta seria o
        // tipo — e não "inicie o atendimento", que mandaria a pessoa dar um
        // passo que não adiantaria nada.
        $funcionario = User::factory()->employee()->create();
        $reservado = $this->tipo(false, 'Segurança');

        $deOutro = Occurrence::factory()->inProgressWith(User::factory()->employee()->create())
            ->create(['occurrence_type_id' => $reservado->id]);

        $this->encerrar($funcionario, $deOutro)
            ->assertJsonPath('message', 'Você só pode encerrar os chamados atribuídos a você.');

        $proprioSemIniciar = Occurrence::factory()->assignedTo($funcionario)
            ->create(['occurrence_type_id' => $reservado->id]);

        $this->encerrar($funcionario, $proprioSemIniciar)
            ->assertJsonPath('message', 'Chamados do tipo Segurança são encerrados pelo síndico ou pela administração.');
    }

    public function test_a_regra_segue_o_caso_de_uso_e_nao_o_papel(): void
    {
        // Conceder close-any-occurrence a um funcionário de confiança o libera
        // de verdade, inclusive para tipos reservados.
        $funcionario = User::factory()->employee()->create();
        $funcionario->givePermissionTo('close-any-occurrence');

        $chamado = Occurrence::factory()->create([
            'occurrence_type_id' => $this->tipo(false, 'Barulho e sossego')->id,
        ]);

        $this->encerrar($funcionario, $chamado)->assertStatus(201);
    }

    public function test_chamado_ja_encerrado_nao_e_encerrado_de_novo(): void
    {
        $sindico = User::factory()->syndic()->create();

        foreach ([OccurrenceStatus::Completed, OccurrenceStatus::Cancelled] as $final) {
            $chamado = Occurrence::factory()->create(['status' => $final]);

            $this->encerrar($sindico, $chamado)
                ->assertStatus(409)
                ->assertJsonPath('message', "Este chamado já está {$final->label()}.");
        }
    }

    public function test_exige_so_o_texto_do_que_foi_feito(): void
    {
        // O status não vem na requisição — finalizar sempre leva a
        // Concluído —, e `resolved` é opcional. O único campo obrigatório é
        // a conclusão.
        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/occurrences/'.Occurrence::factory()->create()->id.'/finish', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.conclusion.0', 'Descreva o que foi feito para finalizar o atendimento.')
            ->assertJsonMissingPath('errors.resolved');
    }

    public function test_finalizar_so_com_o_texto_registra_como_resolvido(): void
    {
        // Quem descreve o que fez para finalizar, na regra, resolveu. O
        // contrário é a exceção, e precisa ser dito com `resolved: false`.
        $chamado = Occurrence::factory()->create();

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson("/api/occurrences/{$chamado->id}/finish", ['conclusion' => 'Registro trocado.'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.resolved', true)
            ->assertJsonPath('data.conclusion', 'Registro trocado.');
    }

    public function test_o_status_enviado_pelo_cliente_e_ignorado(): void
    {
        // "O status será alterado automaticamente pelo backend": mandar outro
        // status junto não muda o desfecho.
        $chamado = Occurrence::factory()->create();

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson("/api/occurrences/{$chamado->id}/finish", ['conclusion' => 'Feito.', 'status' => 'cancelled'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_registra_o_desfecho_no_historico(): void
    {
        $sindico = User::factory()->syndic()->create();
        $chamado = Occurrence::factory()->create();

        $this->encerrar($sindico, $chamado, ['resolved' => false, 'conclusion' => 'Peça fora de linha.']);

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'user_id' => $sindico->id,
            'type' => OccurrenceHistoryType::Completed->value,
            'previous_status' => 'open',
            'new_status' => 'completed',
            'message' => 'Não resolvido. Peça fora de linha.',
        ]);
    }

    public function test_registra_o_encerramento_na_auditoria(): void
    {
        $sindico = User::factory()->syndic()->create(['name' => 'Carlos Síndico']);
        $chamado = Occurrence::factory()->create(['title' => 'Vazamento no corredor']);

        $this->encerrar($sindico, $chamado);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE->value,
            'description' => "Ocorrência {$chamado->id} Vazamento no corredor encerrada como resolvida por Carlos Síndico.",
        ]);
    }

    public function test_o_morador_nao_da_baixa(): void
    {
        $chamado = Occurrence::factory()->create();

        $this->encerrar($chamado->resident, $chamado)->assertStatus(403);
    }

    public function test_chamado_inexistente_responde_404(): void
    {
        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/occurrences/999/finish', ['resolved' => true, 'conclusion' => 'x'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Ocorrência 999 não encontrada.');
    }

    public function test_exige_autenticacao(): void
    {
        $this->postJson('/api/occurrences/1/finish', [])->assertStatus(401);
    }

    public function test_quem_da_baixa_em_qualquer_chamado_enxerga_a_fila_inteira(): void
    {
        // O síndico ganhou close-any-occurrence sem analyze-occurrences. Sem
        // ajustar o escopo, a listagem lhe mostraria só os chamados em que ele
        // é o morador — nenhum — e ele teria o poder de encerrar o que não vê.
        $funcionario = User::factory()->employee()->create();
        $morador = User::factory()->resident()->create();

        Occurrence::factory()->count(2)->create();
        Occurrence::factory()->assignedTo($funcionario)->create();
        Occurrence::factory()->create(['resident_id' => $morador->id]);

        $visiveis = fn (User $user) => ScopeOccurrencesAction::execute(Occurrence::query(), $user)->count();

        $this->assertSame(4, $visiveis(User::factory()->syndic()->create()));
        $this->assertSame(4, $visiveis(User::factory()->admin()->create()));
        $this->assertSame(1, $visiveis($funcionario));
        $this->assertSame(1, $visiveis($morador));
    }
}
