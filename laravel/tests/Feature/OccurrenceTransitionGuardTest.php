<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Actions\AssignOccurrenceAction;
use App\Http\Actions\FinishOccurrenceAction;
use App\Http\Actions\UpdateOccurrenceStatusAction;
use App\Models\Occurrence;
use App\Models\OccurrenceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A máquina de estados aplicada no model.
 *
 * As Actions decidem quem faz cada transição; o model garante que nenhuma
 * gravação tire o chamado do fluxo, venha de onde vier. Conferir só nas
 * Actions deixaria a regra valendo apenas para quem lembrasse de chamá-las.
 */
class OccurrenceTransitionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_gravacao_direta_fora_do_fluxo_e_recusada(): void
    {
        // O caminho que as Actions não cobrem: alguém atualizando o model
        // diretamente, num comando, num job ou num controller futuro.
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        try {
            $chamado->update(['status' => OccurrenceStatus::InProgress]);
            $this->fail('O model deveria ter recusado a transição.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $this->assertSame(OccurrenceStatus::Open, $chamado->fresh()->status);
    }

    public function test_gravacao_direta_dentro_do_fluxo_passa(): void
    {
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        $chamado->update(['status' => OccurrenceStatus::Analysis]);

        $this->assertSame(OccurrenceStatus::Analysis, $chamado->fresh()->status);
    }

    public function test_status_como_texto_tambem_e_conferido(): void
    {
        // O cast converte a string no enum antes do gancho rodar: não há como
        // escapar mandando o valor cru.
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Completed]);

        $this->expectException(BusinessRuleException::class);

        $chamado->update(['status' => 'open']);
    }

    public function test_alterar_outro_campo_nao_passa_pela_maquina(): void
    {
        // A trava olha só o status. Corrigir o título de um chamado encerrado
        // não é transição e não deve ser barrado por ela.
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Completed]);

        $chamado->update(['title' => 'Título corrigido']);

        $this->assertSame('Título corrigido', $chamado->fresh()->title);
    }

    public function test_reatribuir_enquanto_atribuido_nao_e_transicao(): void
    {
        // Só o funcionário muda; o status continua Atribuído.
        $admin = User::factory()->admin()->create();
        $primeiro = User::factory()->employee()->create();
        $segundo = User::factory()->employee()->create();

        $chamado = Occurrence::factory()->assignedTo($primeiro)->create();

        AssignOccurrenceAction::execute($chamado, $admin, $segundo, 'O primeiro entrou de férias.');

        $this->assertSame($segundo->id, $chamado->fresh()->assigned_employee_id);
        $this->assertSame(OccurrenceStatus::Assigned, $chamado->fresh()->status);
    }

    public function test_atribuir_exige_a_analise_antes(): void
    {
        // A fundação aceitava atribuir um chamado Aberto direto, pulando a
        // análise que a especificação e o fluxo do time colocam no meio.
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        try {
            AssignOccurrenceAction::execute($chamado, User::factory()->admin()->create(), User::factory()->employee()->create());
            $this->fail('A atribuição deveria exigir a análise antes.');
        } catch (BusinessRuleException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('Analise o chamado antes de encaminhá-lo a um funcionário.', $e->getMessage());
        }

        $this->assertNull($chamado->fresh()->assigned_employee_id);
    }

    public function test_criar_em_qualquer_etapa_nao_e_conferido(): void
    {
        // De propósito: a única porta de criação da aplicação é a
        // CreateOccurrenceAction, que sempre cria Aberto, e as factories
        // precisam criar chamados em qualquer etapa para os testes
        // exercitarem as transições a partir dela.
        foreach (OccurrenceStatus::cases() as $status) {
            $this->assertSame($status, Occurrence::factory()->create(['status' => $status])->status);
        }
    }

    public function test_o_caminho_da_especificacao_pelas_actions_reais(): void
    {
        // ABERTA → EM_ANALISE → ENCAMINHADA → EM_ATENDIMENTO → FINALIZADA,
        // cada passo dado por quem o fluxo diz, com a trava do model ativa.
        $admin = User::factory()->admin()->create();
        $funcionario = User::factory()->employee()->create();
        $tipo = OccurrenceType::factory()->closableByEmployee()->create();

        $chamado = Occurrence::factory()->create([
            'status' => OccurrenceStatus::Open,
            'occurrence_type_id' => $tipo->id,
        ]);

        $chamado = UpdateOccurrenceStatusAction::execute($chamado, $admin, OccurrenceStatus::Analysis);
        $this->assertSame(OccurrenceStatus::Analysis, $chamado->status);

        $chamado = AssignOccurrenceAction::execute($chamado, $admin, $funcionario);
        $this->assertSame(OccurrenceStatus::Assigned, $chamado->status);

        $chamado = UpdateOccurrenceStatusAction::execute($chamado, $funcionario, OccurrenceStatus::InProgress);
        $this->assertSame(OccurrenceStatus::InProgress, $chamado->status);

        $chamado = FinishOccurrenceAction::execute($chamado, $funcionario, true, 'Registro trocado.');
        $this->assertSame(OccurrenceStatus::Completed, $chamado->status);
        $this->assertTrue($chamado->resolved);

        // Cada passo deixou sua linha no histórico. Não há "created" porque o
        // chamado veio da factory; quem o grava é a CreateOccurrenceAction.
        $this->assertSame(
            ['status_changed', 'assigned', 'status_changed', 'completed'],
            $chamado->histories()->orderBy('id')->get()->map(fn ($linha) => $linha->type->value)->all()
        );
    }
}
