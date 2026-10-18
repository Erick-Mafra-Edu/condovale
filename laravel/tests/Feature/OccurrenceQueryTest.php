<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\OccurrenceType;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RN06 — listagem, detalhe e histórico, cada um vendo só o que lhe cabe.
 *
 * O morador vê os próprios chamados, o funcionário os atribuídos a ele, o
 * síndico e a administração a fila inteira. Das pessoas, sai só id e nome.
 */
class OccurrenceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_morador_lista_so_os_proprios_chamados(): void
    {
        $morador = User::factory()->resident()->create();
        Occurrence::factory()->count(2)->create(['resident_id' => $morador->id]);
        Occurrence::factory()->count(3)->create();

        $this->actingAs($morador)
            ->getJson('/api/occurrences')
            ->assertStatus(201)
            ->assertJsonPath('total', 2);
    }

    public function test_o_funcionario_lista_so_os_atribuidos_a_ele(): void
    {
        $funcionario = User::factory()->employee()->create();
        Occurrence::factory()->assignedTo($funcionario)->create();
        Occurrence::factory()->count(2)->create();

        $this->actingAs($funcionario)
            ->getJson('/api/occurrences')
            ->assertJsonPath('total', 1);
    }

    public function test_sindico_e_administracao_listam_a_fila_inteira(): void
    {
        Occurrence::factory()->count(4)->create();

        foreach ([User::factory()->syndic()->create(), User::factory()->admin()->create()] as $gestor) {
            $this->actingAs($gestor)->getJson('/api/occurrences')->assertJsonPath('total', 4);
        }
    }

    public function test_a_listagem_traz_as_relacoes_sem_dado_pessoal(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);
        $tipo = OccurrenceType::factory()->create(['name' => 'Manutenção predial']);
        $unidade = Unit::factory()->create(['code' => 'A-101']);

        Occurrence::factory()->assignedTo($funcionario)->create([
            'resident_id' => $morador->id,
            'occurrence_type_id' => $tipo->id,
            'unit_id' => $unidade->id,
        ]);

        $this->actingAs($funcionario)
            ->getJson('/api/occurrences')
            ->assertJsonPath('data.0.resident.name', 'Ana Silva')
            ->assertJsonPath('data.0.assigned_employee.name', 'Pedro Manutenção')
            ->assertJsonPath('data.0.occurrence_type.name', 'Manutenção predial')
            ->assertJsonPath('data.0.unit.code', 'A-101')
            ->assertJsonMissingPath('data.0.resident.email')
            ->assertJsonMissingPath('data.0.assigned_employee.email');
    }

    public function test_relacoes_pedidas_pelo_cliente_sao_ignoradas(): void
    {
        // O parâmetro genérico deixaria o funcionário ler o e-mail e as
        // reservas do morador pedindo `resident.reservations`.
        $morador = User::factory()->resident()->create();
        $funcionario = User::factory()->employee()->create();
        Reservation::factory()->create(['resident_id' => $morador->id]);
        Occurrence::factory()->assignedTo($funcionario)->create(['resident_id' => $morador->id]);

        $this->actingAs($funcionario)
            ->getJson('/api/occurrences?relations=resident.reservations')
            ->assertStatus(201)
            ->assertJsonMissingPath('data.0.resident.reservations')
            ->assertJsonMissingPath('data.0.resident.email');
    }

    public function test_filtra_por_status_e_busca_pelo_titulo(): void
    {
        $admin = User::factory()->admin()->create();
        Occurrence::factory()->create(['title' => 'Vazamento no corredor', 'status' => OccurrenceStatus::Open]);
        Occurrence::factory()->create(['title' => 'Lâmpada queimada', 'status' => OccurrenceStatus::Analysis]);

        $this->actingAs($admin)
            ->getJson('/api/occurrences?filters=status:=:analysis')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Lâmpada queimada');

        $this->actingAs($admin)
            ->getJson('/api/occurrences?search=vazamento')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Vazamento no corredor');
    }

    public function test_o_filtro_nao_fura_o_escopo(): void
    {
        // Pedir os chamados de outro morador pelo filtro não adianta: o
        // escopo entra junto, e o resultado é vazio.
        $morador = User::factory()->resident()->create();
        $alheio = Occurrence::factory()->create();

        $this->actingAs($morador)
            ->getJson("/api/occurrences?filters=resident_id:=:{$alheio->resident_id}")
            ->assertJsonPath('total', 0);
    }

    public function test_o_detalhe_traz_o_que_o_acompanhamento_mostra(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $chamado = Occurrence::factory()->create(['resident_id' => $morador->id, 'title' => 'Vazamento no corredor']);

        $this->actingAs($morador)
            ->getJson("/api/occurrences/{$chamado->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Vazamento no corredor')
            ->assertJsonPath('data.resident.name', 'Ana Silva')
            ->assertJsonPath('data.attachments', [])
            ->assertJsonMissingPath('data.resident.email');
    }

    public function test_quem_nao_ve_o_chamado_nao_ve_o_detalhe_nem_o_historico(): void
    {
        $chamado = Occurrence::factory()->create();
        $intruso = User::factory()->resident()->create();

        $this->actingAs($intruso)->getJson("/api/occurrences/{$chamado->id}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem acesso a este chamado.');

        $this->actingAs($intruso)->getJson("/api/occurrences/{$chamado->id}/history")
            ->assertStatus(403);
    }

    public function test_o_historico_conta_a_linha_do_tempo_com_quem_fez_cada_coisa(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrador CondoVale']);
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        $this->actingAs($admin)->patchJson("/api/occurrences/{$chamado->id}", ['status' => 'analysis', 'message' => 'Verificando.']);
        $this->actingAs($admin)->patchJson("/api/occurrences/{$chamado->id}", ['message' => 'Morador avisado.']);

        $this->actingAs($admin)
            ->getJson("/api/occurrences/{$chamado->id}/history")
            ->assertStatus(201)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.type', 'status_changed')
            ->assertJsonPath('data.0.user.name', 'Administrador CondoVale')
            ->assertJsonPath('data.1.type', 'comment')
            ->assertJsonPath('data.1.message', 'Morador avisado.')
            ->assertJsonMissingPath('data.0.user.email');
    }

    public function test_chamado_inexistente_responde_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/occurrences/999')->assertStatus(404);
        $this->actingAs($admin)->getJson('/api/occurrences/999/history')->assertStatus(404);
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/occurrences')->assertStatus(401);
        $this->getJson('/api/occurrences/1')->assertStatus(401);
        $this->getJson('/api/occurrences/1/history')->assertStatus(401);
    }
}
