<?php

namespace Tests\Feature;

use App\Enums\TypeLogEnum;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RN03 — consulta, edição e inativação de unidades, com a autorização das
 * alterações estruturais.
 */
class UnitCrudTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        return User::factory()->admin()->create();
    }

    private function unidade(array $attributes = []): Unit
    {
        return Unit::factory()->create(array_merge([
            'code' => 'A-101',
            'number' => '101',
            'block' => 'Bloco A',
        ], $attributes));
    }

    private function vincular(Unit $unit): UnitOccupancy
    {
        return UnitOccupancy::factory()->owner()->create([
            'unit_id' => $unit->id,
            'user_id' => User::factory()->resident()->create()->id,
            'started_at' => now()->toDateString(),
        ]);
    }

    public function test_lista_as_unidades_paginadas(): void
    {
        $this->unidade();
        $this->unidade(['code' => 'A-102', 'number' => '102']);

        $this->actingAs($this->administrador())
            ->getJson('/api/units')
            ->assertStatus(201)
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_busca_por_codigo_numero_e_bloco(): void
    {
        $this->unidade();
        $this->unidade(['code' => 'B-201', 'number' => '201', 'block' => 'Bloco B']);

        $this->actingAs($this->administrador())
            ->getJson('/api/units?search=B-201')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.code', 'B-201');
    }

    public function test_consulta_uma_unidade(): void
    {
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->getJson("/api/units/{$unidade->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'A-101');
    }

    public function test_unidade_inexistente_responde_404(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/units/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Unidade 999 não encontrada.');
    }

    public function test_cadastra_uma_unidade(): void
    {
        $this->actingAs($this->administrador())
            ->postJson('/api/units', ['code' => 'C-301', 'number' => '301', 'block' => 'Bloco C'])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'C-301')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('units', ['code' => 'C-301']);
    }

    public function test_o_codigo_e_normalizado_em_maiusculas(): void
    {
        // Sem isso "a-101" e "A-101" viram duas unidades, e o vínculo do
        // morador passa a depender de como alguém digitou.
        $this->actingAs($this->administrador())
            ->postJson('/api/units', ['code' => 'c-301', 'number' => '301'])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'C-301');
    }

    public function test_recusa_codigo_repetido(): void
    {
        $this->unidade();

        $this->actingAs($this->administrador())
            ->postJson('/api/units', ['code' => 'A-101', 'number' => '101'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Já existe uma unidade com este código.');
    }

    public function test_exige_o_codigo_no_cadastro(): void
    {
        // O Form Request herdado da fundação não validava o código, que é
        // obrigatório e único na tabela: o cadastro morria no banco.
        $this->actingAs($this->administrador())
            ->postJson('/api/units', ['number' => '301'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Informe o código da unidade.');
    }

    public function test_registra_o_cadastro_na_auditoria(): void
    {
        $this->actingAs($this->administrador())
            ->postJson('/api/units', ['code' => 'C-301', 'number' => '301']);

        $unidade = Unit::where('code', 'C-301')->first();

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::UNIT->value,
            'description' => "Unidade {$unidade->id} C-301 cadastrada.",
        ]);
    }

    public function test_edita_a_unidade(): void
    {
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->patchJson("/api/units/{$unidade->id}", ['number' => '110', 'block' => 'Bloco A1'])
            ->assertStatus(201)
            ->assertJsonPath('data.number', '110')
            ->assertJsonPath('data.block', 'Bloco A1');
    }

    public function test_permite_renumerar_mantendo_o_codigo_unico(): void
    {
        $unidade = $this->unidade();
        $this->unidade(['code' => 'A-102', 'number' => '102']);

        $this->actingAs($this->administrador())
            ->patchJson("/api/units/{$unidade->id}", ['code' => 'A-102'])
            ->assertStatus(422);

        $this->actingAs($this->administrador())
            ->patchJson("/api/units/{$unidade->id}", ['code' => 'A-110'])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'A-110');
    }

    public function test_inativa_sem_apagar(): void
    {
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->postJson("/api/units/{$unidade->id}/deactivate")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('units', ['id' => $unidade->id, 'status' => 'inactive']);
    }

    public function test_recusa_inativar_unidade_com_morador_vinculado(): void
    {
        // Unidade inativa não aceita vínculo novo. Inativar uma que ainda tem
        // morador criaria o estado que o sistema não deixa criar, e sem como
        // refazer o vínculo depois.
        $unidade = $this->unidade();
        $this->vincular($unidade);

        $this->actingAs($this->administrador())
            ->postJson("/api/units/{$unidade->id}/deactivate")
            ->assertStatus(409)
            ->assertJsonPath('message', 'A unidade A-101 ainda tem 1 morador vinculado: encerre os vínculos antes de inativá-la.');
    }

    public function test_inativa_depois_que_o_vinculo_e_encerrado(): void
    {
        $unidade = $this->unidade();
        $vinculo = $this->vincular($unidade);

        $vinculo->update(['is_active' => false, 'ended_at' => now()->toDateString()]);

        $this->actingAs($this->administrador())
            ->postJson("/api/units/{$unidade->id}/deactivate")
            ->assertStatus(201);
    }

    public function test_a_trava_do_vinculo_vale_tambem_pelo_patch(): void
    {
        $unidade = $this->unidade();
        $this->vincular($unidade);

        $this->actingAs($this->administrador())
            ->patchJson("/api/units/{$unidade->id}", ['status' => UnitStatus::Inactive->value])
            ->assertStatus(409);
    }

    public function test_recusa_inativar_duas_vezes(): void
    {
        $unidade = $this->unidade(['status' => UnitStatus::Inactive]);

        $this->actingAs($this->administrador())
            ->postJson("/api/units/{$unidade->id}/deactivate")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Esta unidade já está inativa.');
    }

    public function test_reativa_pelo_patch(): void
    {
        $unidade = $this->unidade(['status' => UnitStatus::Inactive]);

        $this->actingAs($this->administrador())
            ->patchJson("/api/units/{$unidade->id}", ['status' => UnitStatus::Active->value])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_rn03_o_sindico_tambem_altera_unidades(): void
    {
        // "Alterações estruturais de unidades restritas ao
        // Síndico/Administrador": é o único caso de uso administrativo que o
        // síndico divide. O cadastro de pessoas continua só do administrador.
        $unidade = $this->unidade();

        $this->actingAs(User::factory()->syndic()->create())
            ->getJson('/api/units')
            ->assertStatus(201);

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson("/api/units/{$unidade->id}/deactivate")
            ->assertStatus(201);
    }

    public function test_morador_e_funcionario_nao_alteram_unidades(): void
    {
        $unidade = $this->unidade();

        foreach ([UserRole::Resident, UserRole::Employee] as $papel) {
            $usuario = User::factory()->create(['role' => $papel]);

            $this->actingAs($usuario)->getJson('/api/units')->assertStatus(403);
            $this->actingAs($usuario)
                ->postJson("/api/units/{$unidade->id}/deactivate")
                ->assertStatus(403);
        }
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/units')->assertStatus(401);
    }
}
