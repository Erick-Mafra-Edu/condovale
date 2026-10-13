<?php

namespace Tests\Feature;

use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Models\Occurrence;
use App\Models\OccurrenceType;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF04 — a administração mantém a lista de tipos de chamado.
 *
 * A lista era texto livre na coluna `category`: "Barulho", "barulho" e
 * "Perturbação do sossego" viravam três categorias, e o relatório do síndico
 * contava errado justamente o que ele serve para medir. Virou cadastro, e
 * quem o mantém é o síndico ou o administrador, sem depender de deploy.
 */
class OccurrenceTypeCrudTest extends TestCase
{
    use RefreshDatabase;

    private function sindico(): User
    {
        return User::factory()->syndic()->create();
    }

    private function morador(): User
    {
        $morador = User::factory()->resident()->create();

        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);

        return $morador->fresh();
    }

    public function test_lista_os_tipos_paginados(): void
    {
        OccurrenceType::factory()->count(3)->create();

        $this->actingAs($this->sindico())
            ->getJson('/api/occurrence-types')
            ->assertStatus(201)
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_a_listagem_traz_ativos_e_inativos(): void
    {
        // Quem administra precisa enxergar os aposentados para reativá-los;
        // quem monta a lista de escolha do morador filtra com active:=:1.
        OccurrenceType::factory()->create(['name' => 'Manutenção']);
        OccurrenceType::factory()->inactive()->create(['name' => 'Telefonia']);

        $this->actingAs($this->sindico())
            ->getJson('/api/occurrence-types')
            ->assertJsonPath('total', 2);

        $this->actingAs($this->sindico())
            ->getJson('/api/occurrence-types?filters=active:=:1')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Manutenção');
    }

    public function test_busca_por_nome(): void
    {
        OccurrenceType::factory()->create(['name' => 'Estacionamento']);
        OccurrenceType::factory()->create(['name' => 'Barulho e sossego']);

        $this->actingAs($this->sindico())
            ->getJson('/api/occurrence-types?search=sossego')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Barulho e sossego');
    }

    public function test_consulta_um_tipo(): void
    {
        $tipo = OccurrenceType::factory()->create(['name' => 'Segurança']);

        $this->actingAs($this->sindico())
            ->getJson("/api/occurrence-types/{$tipo->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Segurança');
    }

    public function test_tipo_inexistente_responde_404(): void
    {
        $this->actingAs($this->sindico())
            ->getJson('/api/occurrence-types/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Tipo de ocorrência 999 não encontrado.');
    }

    public function test_cria_um_tipo(): void
    {
        $this->actingAs($this->sindico())
            ->postJson('/api/occurrence-types', [
                'name' => 'Pets e animais',
                'description' => 'Circulação de animais fora das regras do condomínio.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Pets e animais')
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('occurrence_types', ['name' => 'Pets e animais']);
    }

    public function test_recusa_nome_repetido(): void
    {
        // Dois tipos com o mesmo nome dividiriam o mesmo assunto em duas
        // linhas do relatório.
        OccurrenceType::factory()->create(['name' => 'Estacionamento']);

        $this->actingAs($this->sindico())
            ->postJson('/api/occurrence-types', ['name' => 'Estacionamento'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Já existe um tipo de ocorrência com este nome.');
    }

    public function test_exige_o_nome(): void
    {
        $this->actingAs($this->sindico())
            ->postJson('/api/occurrence-types', ['description' => 'sem nome'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Informe o nome do tipo de ocorrência.');
    }

    public function test_registra_a_criacao_na_auditoria(): void
    {
        $this->actingAs($this->sindico())
            ->postJson('/api/occurrence-types', ['name' => 'Pets e animais']);

        $tipo = OccurrenceType::where('name', 'Pets e animais')->first();

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE_TYPE->value,
            'description' => "Tipo de ocorrência {$tipo->id} Pets e animais criado.",
        ]);
    }

    public function test_edita_o_tipo(): void
    {
        $tipo = OccurrenceType::factory()->create(['name' => 'Barulo']);

        $this->actingAs($this->sindico())
            ->patchJson("/api/occurrence-types/{$tipo->id}", [
                'name' => 'Barulho e sossego',
                'description' => 'Ruído fora do horário permitido.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Barulho e sossego');
    }

    public function test_renomear_corrige_o_rotulo_dos_chamados_antigos(): void
    {
        // Os chamados apontam para o tipo e não guardam cópia do nome:
        // corrigir a grafia corrige o histórico inteiro, em vez de deixar duas
        // versões convivendo no relatório.
        $tipo = OccurrenceType::factory()->create(['name' => 'Barulo']);
        $chamado = Occurrence::factory()->create(['occurrence_type_id' => $tipo->id]);

        $this->actingAs($this->sindico())
            ->patchJson("/api/occurrence-types/{$tipo->id}", ['name' => 'Barulho e sossego'])
            ->assertStatus(201);

        $this->assertSame('Barulho e sossego', $chamado->fresh()->occurrenceType->name);
    }

    public function test_recusa_renomear_para_um_nome_ja_usado(): void
    {
        OccurrenceType::factory()->create(['name' => 'Estacionamento']);
        $tipo = OccurrenceType::factory()->create(['name' => 'Garagem']);

        $this->actingAs($this->sindico())
            ->patchJson("/api/occurrence-types/{$tipo->id}", ['name' => 'Estacionamento'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Já existe um tipo de ocorrência com este nome.');
    }

    public function test_manter_o_proprio_nome_nao_colide_consigo_mesmo(): void
    {
        $tipo = OccurrenceType::factory()->create(['name' => 'Segurança']);

        $this->actingAs($this->sindico())
            ->patchJson("/api/occurrence-types/{$tipo->id}", [
                'name' => 'Segurança',
                'description' => 'Portaria e controle de acesso.',
            ])
            ->assertStatus(201);
    }

    public function test_inativa_e_reativa_pelo_patch(): void
    {
        $tipo = OccurrenceType::factory()->create();

        $this->actingAs($this->sindico())
            ->patchJson("/api/occurrence-types/{$tipo->id}", ['active' => false])
            ->assertStatus(201)
            ->assertJsonPath('data.active', false);

        $this->actingAs($this->sindico())
            ->patchJson("/api/occurrence-types/{$tipo->id}", ['active' => true])
            ->assertStatus(201)
            ->assertJsonPath('data.active', true);
    }

    public function test_apaga_um_tipo_que_nunca_foi_usado(): void
    {
        $tipo = OccurrenceType::factory()->create(['name' => 'Criado por engano']);

        $this->actingAs($this->sindico())
            ->deleteJson("/api/occurrence-types/{$tipo->id}")
            ->assertStatus(201);

        $this->assertDatabaseMissing('occurrence_types', ['id' => $tipo->id]);
    }

    public function test_recusa_apagar_um_tipo_em_uso(): void
    {
        // Apagar deixaria os chamados antigos sem classificação, e o relatório
        // perderia justamente o histórico que ele serve para mostrar.
        $tipo = OccurrenceType::factory()->create(['name' => 'Manutenção predial']);
        Occurrence::factory()->create(['occurrence_type_id' => $tipo->id]);

        $this->actingAs($this->sindico())
            ->deleteJson("/api/occurrence-types/{$tipo->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', '1 chamado usa o tipo Manutenção predial: inative-o em vez de apagá-lo, para não deixar os chamados antigos sem classificação.');

        $this->assertDatabaseHas('occurrence_types', ['id' => $tipo->id]);
    }

    public function test_registra_a_exclusao_na_auditoria(): void
    {
        $tipo = OccurrenceType::factory()->create(['name' => 'Criado por engano']);

        $this->actingAs($this->sindico())->deleteJson("/api/occurrence-types/{$tipo->id}");

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE_TYPE->value,
            'description' => "Tipo de ocorrência {$tipo->id} Criado por engano excluído.",
        ]);
    }

    public function test_o_administrador_tambem_mantem_a_lista(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/occurrence-types', ['name' => 'Pets e animais'])
            ->assertStatus(201);
    }

    public function test_o_morador_consulta_a_lista_mas_nao_a_altera(): void
    {
        // Ler é insumo para abrir chamado — o morador precisa escolher o tipo.
        // Alterar é ato administrativo.
        $tipo = OccurrenceType::factory()->create();
        $morador = $this->morador();

        $this->actingAs($morador)->getJson('/api/occurrence-types')->assertStatus(201);

        $this->actingAs($morador)
            ->postJson('/api/occurrence-types', ['name' => 'Inventado pelo morador'])
            ->assertStatus(403);

        $this->actingAs($morador)
            ->patchJson("/api/occurrence-types/{$tipo->id}", ['name' => 'Outro nome'])
            ->assertStatus(403);

        $this->actingAs($morador)
            ->deleteJson("/api/occurrence-types/{$tipo->id}")
            ->assertStatus(403);
    }

    public function test_o_funcionario_nao_alcanca_a_lista(): void
    {
        // Consequência da matriz: o funcionário atende chamado, não o abre nem
        // classifica. O tipo chega a ele pela própria ocorrência.
        $this->actingAs(User::factory()->employee()->create())
            ->getJson('/api/occurrence-types')
            ->assertStatus(403);
    }

    public function test_somente_sindico_e_administrador_alteram(): void
    {
        foreach ([UserRole::Resident, UserRole::Employee] as $papel) {
            $this->actingAs(User::factory()->create(['role' => $papel]))
                ->postJson('/api/occurrence-types', ['name' => "Tipo do {$papel->value}"])
                ->assertStatus(403);
        }
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/occurrence-types')->assertStatus(401);
        $this->postJson('/api/occurrence-types', ['name' => 'X'])->assertStatus(401);
    }
}
