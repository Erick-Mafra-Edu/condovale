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
 * Ocorrencia.encaminhar() — RN06 / RN15.
 *
 * Administração e síndico encaminham qualquer chamado, a partir da análise. O
 * funcionário repassa só o que é dele. Repassar — o chamado já tinha
 * responsável — exige justificativa de quem quer que seja.
 */
class OccurrenceAssignTest extends TestCase
{
    use RefreshDatabase;

    private function emAnalise(array $attributes = []): Occurrence
    {
        return Occurrence::factory()->create(array_merge([
            'title' => 'Vazamento no corredor',
            'status' => OccurrenceStatus::Analysis,
        ], $attributes));
    }

    private function encaminhar(User $autor, Occurrence $chamado, User $destino, ?string $justificativa = null)
    {
        return $this->actingAs($autor)->patchJson(
            "/api/occurrences/{$chamado->id}/assign",
            array_filter(['employee_id' => $destino->id, 'message' => $justificativa])
        );
    }

    public function test_a_administracao_encaminha_a_um_funcionario(): void
    {
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $chamado = $this->emAnalise();

        $this->encaminhar(User::factory()->admin()->create(), $chamado, $funcionario)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_employee_id', $funcionario->id)
            ->assertJsonPath('data.assigned_employee.name', 'Pedro Manutenção')
            ->assertJsonMissingPath('data.assigned_employee.email')
            ->assertJsonPath('message', "Ocorrência {$chamado->id} encaminhada a Pedro Manutenção.");
    }

    public function test_o_sindico_analisa_e_encaminha(): void
    {
        // O caminho inteiro, pelo síndico: sem a análise, ele só encaminharia
        // o que o administrador já tivesse analisado.
        $sindico = User::factory()->syndic()->create();
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        $this->actingAs($sindico)
            ->patchJson("/api/occurrences/{$chamado->id}/status", ['status' => 'analysis'])
            ->assertStatus(201);

        $this->encaminhar($sindico, $chamado, $funcionario)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'assigned');
    }

    public function test_nao_se_encaminha_sem_analisar(): void
    {
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        $this->encaminhar(User::factory()->admin()->create(), $chamado, User::factory()->employee()->create())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Analise o chamado antes de encaminhá-lo a um funcionário.');
    }

    public function test_chamado_encerrado_nao_e_encaminhado(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([OccurrenceStatus::Completed, OccurrenceStatus::Cancelled] as $final) {
            $this->encaminhar($admin, $this->emAnalise(['status' => $final]), User::factory()->employee()->create())
                ->assertStatus(409)
                ->assertJsonPath('message', "Este chamado já está {$final->label()}.");
        }
    }

    public function test_quem_recebe_precisa_atender_chamados(): void
    {
        $admin = User::factory()->admin()->create();
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);

        $this->encaminhar($admin, $this->emAnalise(), $morador)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ana Silva não atende chamados: escolha um funcionário.');
    }

    public function test_quem_recebe_precisa_estar_ativo(): void
    {
        $inativo = User::factory()->employee()->inactive()->create(['name' => 'Joana Desligada']);

        $this->encaminhar(User::factory()->admin()->create(), $this->emAnalise(), $inativo)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Joana Desligada está inativo e não pode receber chamados.');
    }

    public function test_quem_atende_por_concessao_individual_tambem_recebe(): void
    {
        // O destino é conferido pelo caso de uso, não pelo papel: um síndico
        // a quem se concedeu atender chamados pode recebê-los.
        $sindico = User::factory()->syndic()->create();
        $sindico->givePermissionTo('update-occurrence-progress');

        $this->encaminhar(User::factory()->admin()->create(), $this->emAnalise(), $sindico)
            ->assertStatus(201);
    }

    public function test_recusa_encaminhar_para_quem_ja_e_o_responsavel(): void
    {
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $chamado = Occurrence::factory()->assignedTo($funcionario)->create();

        $this->encaminhar(User::factory()->admin()->create(), $chamado, $funcionario, 'motivo')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Pedro Manutenção já é o responsável por este chamado.');
    }

    public function test_repassar_exige_justificativa(): void
    {
        // Quem recebe precisa saber por que o chamado mudou de mãos.
        $primeiro = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $segundo = User::factory()->employee()->create(['name' => 'Rafael Elétrica']);
        $chamado = Occurrence::factory()->assignedTo($primeiro)->create();

        $this->encaminhar(User::factory()->admin()->create(), $chamado, $segundo)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe por que o chamado está sendo repassado de Pedro Manutenção para Rafael Elétrica.');

        $this->assertSame($primeiro->id, $chamado->fresh()->assigned_employee_id);
    }

    public function test_repasse_com_justificativa_fica_no_historico(): void
    {
        $admin = User::factory()->admin()->create();
        $primeiro = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $segundo = User::factory()->employee()->create(['name' => 'Rafael Elétrica']);
        $chamado = Occurrence::factory()->assignedTo($primeiro)->create();

        $this->encaminhar($admin, $chamado, $segundo, 'Problema é elétrico, não hidráulico.')
            ->assertStatus(201)
            ->assertJsonPath('data.assigned_employee_id', $segundo->id);

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'user_id' => $admin->id,
            'type' => OccurrenceHistoryType::Assigned->value,
            'message' => 'Repassado de Pedro Manutenção para Rafael Elétrica: Problema é elétrico, não hidráulico.',
        ]);
    }

    public function test_atendimento_iniciado_volta_a_atribuido_ao_ser_repassado(): void
    {
        // Quem recebe precisa iniciar o próprio atendimento: o andamento era
        // do anterior.
        $primeiro = User::factory()->employee()->create();
        $segundo = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($primeiro)->create();

        $this->encaminhar(User::factory()->admin()->create(), $chamado, $segundo, 'Troca de especialidade.')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'assigned');
    }

    public function test_o_funcionario_repassa_o_proprio_chamado(): void
    {
        $funcionario = User::factory()->employee()->create();
        $colega = User::factory()->employee()->create();

        foreach ([OccurrenceStatus::Assigned, OccurrenceStatus::InProgress] as $status) {
            $chamado = Occurrence::factory()->create(['assigned_employee_id' => $funcionario->id, 'status' => $status]);

            $this->encaminhar($funcionario, $chamado, $colega, 'Problema fora da minha especialidade.')
                ->assertStatus(201)
                ->assertJsonPath('data.assigned_employee_id', $colega->id)
                ->assertJsonPath('data.status', 'assigned');
        }
    }

    public function test_o_funcionario_sempre_justifica_o_repasse(): void
    {
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->assignedTo($funcionario)->create();

        $this->encaminhar($funcionario, $chamado, User::factory()->employee()->create())
            ->assertStatus(422);
    }

    public function test_o_funcionario_nao_repassa_chamado_de_colega(): void
    {
        // Ele nem enxerga o chamado do colega: a recusa vem do acesso.
        $chamado = Occurrence::factory()->assignedTo(User::factory()->employee()->create())->create();

        $this->encaminhar(User::factory()->employee()->create(), $chamado, User::factory()->employee()->create(), 'motivo')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem acesso a este chamado.');
    }

    public function test_o_funcionario_nao_encaminha_chamado_em_analise(): void
    {
        // Encaminhar o que está em análise é da administração e do síndico.
        $this->encaminhar(User::factory()->employee()->create(), $this->emAnalise(), User::factory()->employee()->create())
            ->assertStatus(403);
    }

    public function test_quem_tem_o_caso_de_uso_de_encaminhar_encaminha_qualquer_um(): void
    {
        // A regra segue o caso de uso: um funcionário a quem se concedeu
        // assign-occurrence encaminha como a administração.
        $funcionario = User::factory()->employee()->create();
        $funcionario->givePermissionTo('assign-occurrence', 'analyze-occurrences');

        $this->encaminhar($funcionario, $this->emAnalise(), User::factory()->employee()->create())
            ->assertStatus(201);
    }

    public function test_registra_o_encaminhamento_no_historico_e_na_auditoria(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrador CondoVale']);
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $chamado = $this->emAnalise();

        $this->encaminhar($admin, $chamado, $funcionario, 'Prioridade: água no elevador.');

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'user_id' => $admin->id,
            'type' => OccurrenceHistoryType::Assigned->value,
            'previous_status' => 'analysis',
            'new_status' => 'assigned',
            'message' => 'Encaminhado a Pedro Manutenção. Prioridade: água no elevador.',
        ]);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE->value,
            'description' => "Ocorrência {$chamado->id} Vazamento no corredor encaminhada a Pedro Manutenção por Administrador CondoVale.",
        ]);
    }

    public function test_o_morador_nao_encaminha(): void
    {
        $chamado = $this->emAnalise();

        $this->encaminhar($chamado->resident, $chamado, User::factory()->employee()->create())
            ->assertStatus(403);
    }

    public function test_exige_o_funcionario(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/occurrences/{$this->emAnalise()->id}/assign", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.employee_id.0', 'Selecione o funcionário responsável.');
    }

    public function test_chamado_inexistente_responde_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson('/api/occurrences/999/assign', ['employee_id' => User::factory()->employee()->create()->id])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Ocorrência 999 não encontrada.');
    }

    public function test_exige_autenticacao(): void
    {
        $this->patchJson('/api/occurrences/1/assign', ['employee_id' => 1])->assertStatus(401);
        $this->getJson('/api/assignable-employees')->assertStatus(401);
    }

    public function test_lista_quem_pode_receber_o_chamado(): void
    {
        // Só funcionários ativos que atendem chamados, só id e nome.
        User::factory()->employee()->create(['name' => 'Bruno Elétrica']);
        User::factory()->employee()->create(['name' => 'Ana Hidráulica']);
        User::factory()->employee()->inactive()->create(['name' => 'Carla Desligada']);
        User::factory()->resident()->create(['name' => 'Daniel Morador']);

        $this->actingAs(User::factory()->syndic()->create())
            ->getJson('/api/assignable-employees')
            ->assertStatus(201)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.name', 'Ana Hidráulica')
            ->assertJsonPath('data.1.name', 'Bruno Elétrica')
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_o_funcionario_nao_aparece_na_propria_lista(): void
    {
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        User::factory()->employee()->create(['name' => 'Rafael Elétrica']);

        $this->actingAs($funcionario)
            ->getJson('/api/assignable-employees')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Rafael Elétrica');
    }

    public function test_o_morador_nao_lista_funcionarios(): void
    {
        $this->actingAs(User::factory()->resident()->create())
            ->getJson('/api/assignable-employees')
            ->assertStatus(403);
    }
}
