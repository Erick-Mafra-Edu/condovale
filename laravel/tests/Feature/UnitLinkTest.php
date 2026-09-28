<?php

namespace Tests\Feature;

use App\Enums\OccupantType;
use App\Enums\TypeLogEnum;
use App\Enums\UnitStatus;
use App\Enums\UserStatus;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vínculo entre morador e unidade — diagrama de sequência "Gerenciamento de
 * Moradores e Unidades" e associação Usuario-Unidade do diagrama de classes.
 */
class UnitLinkTest extends TestCase
{
    use RefreshDatabase;

    private function unidade(array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'block' => 'Bloco A',
            'number' => '101',
            'code' => 'A-101',
        ], $attributes));
    }

    private function administrador(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_administrador_vincula_morador_a_unidade(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->postJson('/api/unit-occupancies', [
                'user_id' => $morador->id,
                'unit_id' => $unidade->id,
                'occupant_type' => OccupantType::Owner->value,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.user_id', $morador->id)
            ->assertJsonPath('data.unit_id', $unidade->id)
            ->assertJsonPath('data.occupant_type', 'owner')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('unit_occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $unidade->id,
            'is_active' => true,
            'ended_at' => null,
        ]);
    }

    public function test_mantem_a_unidade_vigente_do_morador_em_acordo_com_o_vinculo(): void
    {
        $morador = User::factory()->resident()->create();
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->postJson('/api/unit-occupancies', [
                'user_id' => $morador->id,
                'unit_id' => $unidade->id,
            ])
            ->assertStatus(201);

        $this->assertSame($unidade->id, $morador->fresh()->unit_id);
    }

    public function test_registra_o_vinculo_na_trilha_de_auditoria(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->postJson('/api/unit-occupancies', [
                'user_id' => $morador->id,
                'unit_id' => $unidade->id,
            ]);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::UNIT_OCCUPANCY->value,
            'description' => "Morador {$morador->id} Ana Silva vinculado à unidade {$unidade->id} A-101.",
        ]);
    }

    public function test_permite_vincular_o_mesmo_morador_a_mais_de_uma_unidade(): void
    {
        // O diagrama de classes especifica Usuario "0..*" -- "0..*" Unidade.
        $morador = User::factory()->resident()->create();
        $primeira = $this->unidade();
        $segunda = $this->unidade(['number' => '202', 'code' => 'B-202', 'block' => 'Bloco B']);

        $admin = $this->administrador();

        $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $primeira->id,
        ])->assertStatus(201);

        $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $segunda->id,
        ])->assertStatus(201);

        $this->assertSame(2, $morador->fresh()->activeUnitOccupancies()->count());
    }

    public function test_recusa_vinculo_duplicado_com_a_mesma_unidade(): void
    {
        $morador = User::factory()->resident()->create();
        $unidade = $this->unidade();
        $admin = $this->administrador();

        $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $unidade->id,
        ])->assertStatus(201);

        $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $unidade->id,
        ])->assertStatus(409)
            ->assertJsonPath('message', 'O morador já está vinculado à unidade A-101.');
    }

    public function test_recusa_vincular_quem_nao_e_morador(): void
    {
        $funcionario = User::factory()->employee()->create();
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->postJson('/api/unit-occupancies', [
                'user_id' => $funcionario->id,
                'unit_id' => $unidade->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Somente usuários com perfil de morador podem ser vinculados a uma unidade.');
    }

    public function test_recusa_vincular_usuario_inativo(): void
    {
        $morador = User::factory()->resident()->create(['status' => UserStatus::Inactive]);
        $unidade = $this->unidade();

        $this->actingAs($this->administrador())
            ->postJson('/api/unit-occupancies', [
                'user_id' => $morador->id,
                'unit_id' => $unidade->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível vincular um usuário inativo.');
    }

    public function test_recusa_vincular_a_unidade_inativa(): void
    {
        $morador = User::factory()->resident()->create();
        $unidade = $this->unidade(['status' => UnitStatus::Inactive]);

        $this->actingAs($this->administrador())
            ->postJson('/api/unit-occupancies', [
                'user_id' => $morador->id,
                'unit_id' => $unidade->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A unidade A-101 está inativa.');
    }

    public function test_valida_os_campos_em_portugues(): void
    {
        $this->actingAs($this->administrador())
            ->postJson('/api/unit-occupancies', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Informe o morador.')
            ->assertJsonPath('errors.unit_id.0', 'Informe a unidade.');
    }

    public function test_encerra_o_vinculo_sem_apagar_o_historico(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $unidade = $this->unidade();
        $admin = $this->administrador();

        $criado = $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $unidade->id,
        ])->json('data.id');

        $this->actingAs($admin)
            ->deleteJson("/api/unit-occupancies/{$criado}")
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('unit_occupancies', ['id' => $criado, 'is_active' => false]);
        $this->assertNotNull(UnitOccupancy::find($criado)->ended_at);
        $this->assertNull($morador->fresh()->unit_id);
    }

    public function test_ao_encerrar_reaponta_a_unidade_vigente_para_a_que_sobrou(): void
    {
        $morador = User::factory()->resident()->create();
        $primeira = $this->unidade();
        $segunda = $this->unidade(['number' => '202', 'code' => 'B-202']);
        $admin = $this->administrador();

        $primeiroVinculo = $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $primeira->id,
            'started_at' => now()->subYear()->toDateString(),
        ])->json('data.id');

        $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $segunda->id,
        ])->assertStatus(201);

        $this->actingAs($admin)->deleteJson("/api/unit-occupancies/{$primeiroVinculo}")->assertStatus(201);

        $this->assertSame($segunda->id, $morador->fresh()->unit_id);
    }

    public function test_recusa_encerrar_vinculo_ja_encerrado(): void
    {
        $morador = User::factory()->resident()->create();
        $unidade = $this->unidade();
        $admin = $this->administrador();

        $criado = $this->actingAs($admin)->postJson('/api/unit-occupancies', [
            'user_id' => $morador->id,
            'unit_id' => $unidade->id,
        ])->json('data.id');

        $this->actingAs($admin)->deleteJson("/api/unit-occupancies/{$criado}")->assertStatus(201);

        $this->actingAs($admin)
            ->deleteJson("/api/unit-occupancies/{$criado}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este vínculo já está encerrado.');
    }

    public function test_vinculo_inexistente_responde_404(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/unit-occupancies/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Vínculo 999 não encontrado.');
    }

    public function test_lista_os_vinculos_paginados(): void
    {
        $unidade = $this->unidade();
        $admin = $this->administrador();

        foreach (range(1, 3) as $indice) {
            $morador = User::factory()->resident()->create();

            $this->actingAs($admin)->postJson('/api/unit-occupancies', [
                'user_id' => $morador->id,
                'unit_id' => $unidade->id,
            ])->assertStatus(201);
        }

        $this->actingAs($admin)
            ->getJson('/api/unit-occupancies')
            ->assertStatus(201)
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_somente_o_administrador_vincula(): void
    {
        $morador = User::factory()->resident()->create();
        $unidade = $this->unidade();
        $payload = ['user_id' => $morador->id, 'unit_id' => $unidade->id];

        foreach ([User::factory()->resident(), User::factory()->syndic(), User::factory()->employee()] as $papel) {
            $this->actingAs($papel->create())
                ->postJson('/api/unit-occupancies', $payload)
                ->assertStatus(403);
        }
    }

    public function test_exige_autenticacao(): void
    {
        $this->postJson('/api/unit-occupancies', [])->assertStatus(401);
    }
}
