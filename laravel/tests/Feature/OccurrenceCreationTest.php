<?php

namespace Tests\Feature;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Models\Occurrence;
use App\Models\OccurrenceType;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC04 / RF04 / RN05 — abertura de chamado de ocorrência.
 *
 * O chamado é sempre de um morador e nasce com status. Quem abre nem sempre é
 * quem responde por ele: o síndico abre no lugar de quem não conseguir, e aí o
 * chamado é do morador enquanto o histórico guarda quem o registrou.
 */
class OccurrenceCreationTest extends TestCase
{
    use RefreshDatabase;

    private function tipo(array $attributes = []): OccurrenceType
    {
        return OccurrenceType::factory()->create(array_merge(['name' => 'Manutenção predial'], $attributes));
    }

    /** Morador com unidade: sem vínculo o `unit.linked` barra antes da regra. */
    private function morador(array $attributes = []): User
    {
        $morador = User::factory()->resident()->create($attributes);

        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);

        return $morador->fresh();
    }

    private function corpo(OccurrenceType $tipo, array $extra = []): array
    {
        return array_merge([
            'title' => 'Vazamento no corredor',
            'description' => 'Há água escorrendo do teto perto do elevador.',
            'occurrence_type_id' => $tipo->id,
        ], $extra);
    }

    public function test_o_morador_abre_o_proprio_chamado(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador(['name' => 'Ana Silva']);

        $this->actingAs($morador)
            ->postJson('/api/occurrences', $this->corpo($tipo))
            ->assertStatus(201)
            ->assertJsonPath('data.resident_id', $morador->id)
            ->assertJsonPath('data.unit_id', $morador->unit_id)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.occurrence_type_id', $tipo->id)
            ->assertJsonPath('data.occurrence_type.name', 'Manutenção predial');

        $this->assertDatabaseHas('occurrences', [
            'resident_id' => $morador->id,
            'title' => 'Vazamento no corredor',
            'status' => OccurrenceStatus::Open->value,
        ]);
    }

    public function test_rn05_o_chamado_nasce_com_morador_e_status(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador();

        $resposta = $this->actingAs($morador)->postJson('/api/occurrences', $this->corpo($tipo));

        $this->assertNotNull($resposta->json('data.resident_id'));
        $this->assertSame('open', $resposta->json('data.status'));
    }

    public function test_o_morador_nao_abre_chamado_no_nome_de_outro(): void
    {
        // A porta por onde um morador viraria autor de problema alheio. O id
        // do dono vem da sessão para quem não tem o caso de uso de abrir no
        // lugar de terceiros.
        $tipo = $this->tipo();
        $morador = $this->morador();
        $outro = $this->morador();

        $this->actingAs($morador)
            ->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $outro->id]))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você só pode abrir chamados em seu próprio nome.');

        $this->assertDatabaseCount('occurrences', 0);
    }

    public function test_informar_o_proprio_id_nao_e_abrir_no_nome_de_outro(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador();

        $this->actingAs($morador)
            ->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $morador->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.resident_id', $morador->id);
    }

    public function test_o_sindico_abre_chamado_para_um_morador(): void
    {
        // O caso que motiva o campo: o morador procurou a portaria porque não
        // consegue usar o sistema.
        $tipo = $this->tipo();
        $morador = $this->morador(['name' => 'Ana Silva']);
        $sindico = User::factory()->syndic()->create(['name' => 'Carlos Síndico']);

        $this->actingAs($sindico)
            ->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $morador->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.resident_id', $morador->id)
            ->assertJsonPath('data.unit_id', $morador->unit_id);
    }

    public function test_o_historico_guarda_quem_registrou_o_chamado_de_outro(): void
    {
        // O chamado é do morador, mas quem digitou foi o síndico. Sem isto, o
        // morador apareceria tendo aberto algo que não abriu.
        $tipo = $this->tipo();
        $morador = $this->morador(['name' => 'Ana Silva']);
        $sindico = User::factory()->syndic()->create(['name' => 'Carlos Síndico']);

        $this->actingAs($sindico)->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $morador->id]));

        $this->assertDatabaseHas('occurrence_histories', [
            'user_id' => $sindico->id,
            'type' => OccurrenceHistoryType::Created->value,
            'new_status' => OccurrenceStatus::Open->value,
            'message' => 'Chamado registrado por Carlos Síndico a pedido do morador.',
        ]);
    }

    public function test_o_historico_do_chamado_proprio_nao_tem_recado(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador();

        $this->actingAs($morador)->postJson('/api/occurrences', $this->corpo($tipo));

        $this->assertDatabaseHas('occurrence_histories', [
            'user_id' => $morador->id,
            'type' => OccurrenceHistoryType::Created->value,
            'message' => null,
        ]);
    }

    public function test_o_administrador_tambem_abre_para_um_morador(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $morador->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.resident_id', $morador->id);
    }

    public function test_o_sindico_precisa_dizer_para_quem_e_o_chamado(): void
    {
        // Síndico não mora numa unidade: sem morador informado o chamado
        // nasceria sem de onde partiu nem a quem responder.
        $tipo = $this->tipo();

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/occurrences', $this->corpo($tipo))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe o morador para quem o chamado está sendo aberto.');
    }

    public function test_recusa_abrir_chamado_para_quem_nao_e_morador(): void
    {
        $tipo = $this->tipo();
        $funcionario = User::factory()->employee()->create(['name' => 'Pedro Manutenção']);

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $funcionario->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', "O usuário {$funcionario->id} Pedro Manutenção não é morador: o chamado precisa ser aberto para um morador.");
    }

    public function test_recusa_abrir_chamado_para_morador_inativo(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador(['name' => 'Joana Desligada']);
        $morador->update(['status' => 'inactive']);

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $morador->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', "O morador {$morador->id} Joana Desligada está inativo.");
    }

    public function test_recusa_abrir_chamado_para_morador_sem_unidade(): void
    {
        $tipo = $this->tipo();
        $semUnidade = User::factory()->resident()->create(['name' => 'Carla Sem Unidade']);

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/occurrences', $this->corpo($tipo, ['resident_id' => $semUnidade->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', "O morador {$semUnidade->id} Carla Sem Unidade não está vinculado a uma unidade.");
    }

    public function test_o_morador_sem_unidade_nao_abre_chamado(): void
    {
        // É o `unit.linked`: chamado sem unidade não tem para onde a
        // manutenção ir.
        $tipo = $this->tipo();

        $this->actingAs(User::factory()->resident()->create())
            ->postJson('/api/occurrences', $this->corpo($tipo))
            ->assertStatus(403);
    }

    public function test_exige_o_tipo_da_ocorrencia(): void
    {
        $morador = $this->morador();

        $this->actingAs($morador)
            ->postJson('/api/occurrences', [
                'title' => 'Vazamento no corredor',
                'description' => 'Há água escorrendo do teto.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.occurrence_type_id.0', 'Informe o tipo da ocorrência.');
    }

    public function test_recusa_tipo_inativo(): void
    {
        // Tipo aposentado continua válido nos chamados antigos, mas não entra
        // em chamado novo — do contrário aposentá-lo não significaria nada.
        $tipo = $this->tipo(['active' => false]);
        $morador = $this->morador();

        $this->actingAs($morador)
            ->postJson('/api/occurrences', $this->corpo($tipo))
            ->assertStatus(422)
            ->assertJsonPath('errors.occurrence_type_id.0', 'O tipo de ocorrência informado não existe ou está inativo.');
    }

    public function test_exige_titulo_e_descricao(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador();

        $this->actingAs($morador)
            ->postJson('/api/occurrences', ['occurrence_type_id' => $tipo->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'Informe o título da ocorrência.')
            ->assertJsonPath('errors.description.0', 'Descreva a ocorrência.');
    }

    public function test_registra_a_abertura_na_auditoria(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador(['name' => 'Ana Silva']);

        $this->actingAs($morador)->postJson('/api/occurrences', $this->corpo($tipo));

        $chamado = Occurrence::first();

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE->value,
            'description' => "Ocorrência {$chamado->id} Vazamento no corredor aberta pelo morador {$morador->id} Ana Silva.",
        ]);
    }

    public function test_o_funcionario_nao_abre_chamado(): void
    {
        // A matriz dá ao funcionário atender chamado, não abrir.
        $tipo = $this->tipo();

        $this->actingAs(User::factory()->employee()->create())
            ->postJson('/api/occurrences', $this->corpo($tipo))
            ->assertStatus(403);
    }

    public function test_exige_autenticacao(): void
    {
        $this->postJson('/api/occurrences', [])->assertStatus(401);
    }

    public function test_a_conta_de_primeiro_acesso_nao_abre_chamado(): void
    {
        $tipo = $this->tipo();
        $morador = $this->morador();
        $morador->update(['must_change_password' => true]);

        $this->actingAs($morador->fresh())
            ->postJson('/api/occurrences', $this->corpo($tipo))
            ->assertStatus(403)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_todo_papel_que_abre_chamado_esta_coberto(): void
    {
        // A matriz muda com o tempo; este caso garante que quem ganhar o caso
        // de uso continue entrando pela rota, e quem não tiver continue fora.
        $tipo = $this->tipo();
        $morador = $this->morador();

        $esperado = [
            UserRole::Resident->value => 201,
            UserRole::Syndic->value => 201,
            UserRole::Admin->value => 201,
            UserRole::Employee->value => 403,
        ];

        foreach ($esperado as $papel => $status) {
            $autor = $papel === UserRole::Resident->value ? $this->morador() : User::factory()->create(['role' => $papel]);

            $corpo = $papel === UserRole::Resident->value
                ? $this->corpo($tipo)
                : $this->corpo($tipo, ['resident_id' => $morador->id]);

            $this->actingAs($autor)
                ->postJson('/api/occurrences', $corpo)
                ->assertStatus($status);
        }
    }

    public function test_lista_para_quem_o_sindico_pode_abrir_chamado(): void
    {
        // Só quem a abertura aceitaria: morador ativo e com unidade vigente.
        $ana = $this->morador(['name' => 'Ana Silva']);
        $this->morador(['name' => 'Bruno Costa']);
        User::factory()->resident()->create(['name' => 'Carla Sem Unidade']);
        $this->morador(['name' => 'Joana Desligada', 'status' => 'inactive']);
        User::factory()->employee()->create(['name' => 'Pedro Manutenção']);

        $this->actingAs(User::factory()->syndic()->create())
            ->getJson('/api/occurrence-residents')
            ->assertStatus(201)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.id', $ana->id)
            ->assertJsonPath('data.0.name', 'Ana Silva')
            ->assertJsonPath('data.0.unit.code', $ana->unit->code)
            ->assertJsonPath('data.1.name', 'Bruno Costa')
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_a_lista_de_moradores_aceita_busca_pelo_nome(): void
    {
        $this->morador(['name' => 'Ana Silva']);
        $this->morador(['name' => 'Bruno Costa']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/occurrence-residents?search=bruno')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Bruno Costa');
    }

    public function test_so_quem_abre_chamado_para_outro_lista_os_moradores(): void
    {
        $this->getJson('/api/occurrence-residents')->assertStatus(401);

        foreach ([$this->morador(), User::factory()->employee()->create()] as $usuario) {
            $this->actingAs($usuario)
                ->getJson('/api/occurrence-residents')
                ->assertStatus(403);
        }
    }
}
