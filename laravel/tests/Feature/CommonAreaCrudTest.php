<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
use App\Models\CommonArea;
use App\Models\Log;
use App\Models\Reservation;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AreaComum — cadastro, consulta, edição (com a situação ativa) e exclusão.
 *
 * Consultar é de quem reserva e da administração; escrever, só da
 * administração (manage-reservations). Os horários das áreas e reservas são
 * gravados como "HH:MM:SS", que é como o MySQL de produção os devolve.
 */
class CommonAreaCrudTest extends TestCase
{
    use RefreshDatabase;

    private const HOJE = '2026-11-23';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::HOJE.' 14:10:00'));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function area(array $attributes = []): CommonArea
    {
        return CommonArea::factory()->create([
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'max_reservation_minutes' => 120,
            ...$attributes,
        ]);
    }

    private function reserva(CommonArea $area, string $data, ?string $inicio, ?string $fim, ReservationStatus $status = ReservationStatus::Approved): Reservation
    {
        return Reservation::factory()->create([
            'common_area_id' => $area->id,
            'date' => $data,
            'start_time' => $inicio,
            'end_time' => $fim,
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> */
    private function formulario(array $attributes = []): array
    {
        return [
            'name' => 'Piscina',
            'description' => 'Piscina adulto e infantil.',
            'opening_time' => '09:00',
            'closing_time' => '20:00',
            'max_reservation_minutes' => 180,
            'capacity' => 30,
            ...$attributes,
        ];
    }

    public function test_quem_reserva_lista_todas_as_areas_em_ordem_de_nome_e_numa_pagina_so(): void
    {
        $this->area(['name' => 'Salão de festas']);
        $this->area(['name' => 'Churrasqueira', 'status' => 'unavailable']);
        $this->area(['name' => 'Academia']);

        $resposta = $this->actingAs(User::factory()->resident()->create())
            ->getJson('/api/common-areas')
            ->assertStatus(201)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('amount', 3)
            ->assertJsonPath('data.0.opening_time', '08:00')
            ->assertJsonPath('data.0.closing_time', '22:00');

        // A indisponível também vem: a tela de reservas a mostra como "Indisponível".
        $this->assertSame(['Academia', 'Churrasqueira', 'Salão de festas'], array_column($resposta->json('data'), 'name'));
    }

    public function test_a_listagem_aceita_busca_e_descarta_relations(): void
    {
        $quadra = $this->area(['name' => 'Quadra']);
        $this->area(['name' => 'Piscina']);
        $this->reserva($quadra, '2026-11-24', '10:00:00', '11:00:00');

        $resposta = $this->actingAs(User::factory()->resident()->create())
            ->getJson('/api/common-areas?search=quad&relations=reservations')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Quadra');

        // Por `relations` sairia cada reserva da área, com quem reservou.
        $this->assertArrayNotHasKey('reservations', $resposta->json('data.0'));
    }

    public function test_detalhe_da_area(): void
    {
        $area = $this->area(['name' => 'Quadra']);

        $this->actingAs(User::factory()->resident()->create())
            ->getJson("/api/common-areas/{$area->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Quadra')
            ->assertJsonPath('data.opening_time', '08:00');

        $this->actingAs(User::factory()->resident()->create())
            ->getJson('/api/common-areas/999')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Área comum 999 não encontrada.');
    }

    public function test_a_administracao_cadastra_a_area(): void
    {
        $admin = $this->admin();

        $resposta = $this->actingAs($admin)->postJson('/api/common-areas', $this->formulario())
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Piscina')
            ->assertJsonPath('data.opening_time', '09:00')
            ->assertJsonPath('data.closing_time', '20:00')
            ->assertJsonPath('data.max_reservation_minutes', 180)
            ->assertJsonPath('data.capacity', 30)
            // Sem dizer o contrário, a área nasce aceitando reserva e sem aprovação.
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.requires_approval', false);

        $id = $resposta->json('data.id');
        $resposta->assertJsonPath('message', "Área comum {$id} Piscina criada.");

        $log = Log::where('type_log_id', TypeLogEnum::COMMON_AREA->value)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame("Área comum {$id} Piscina criada.", $log->description);
    }

    public function test_cadastro_com_aprovacao_indisponivel_e_sem_capacidade(): void
    {
        $this->actingAs($this->admin())->postJson('/api/common-areas', $this->formulario([
            'requires_approval' => true,
            'status' => 'unavailable',
            'capacity' => null,
            'max_reservation_minutes' => null,
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.requires_approval', true)
            ->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath('data.capacity', 0)
            ->assertJsonPath('data.max_reservation_minutes', null);
    }

    public function test_validacao_do_cadastro(): void
    {
        $admin = $this->admin();
        $this->area(['name' => 'Quadra']);

        $this->actingAs($admin)->postJson('/api/common-areas', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => 'Informe o nome da área comum.',
                'opening_time' => 'Informe o horário de abertura.',
                'closing_time' => 'Informe o horário de fechamento.',
            ]);

        $this->actingAs($admin)->postJson('/api/common-areas', $this->formulario([
            'name' => 'Quadra',
            'opening_time' => '20:00',
            'closing_time' => '08:00',
            'max_reservation_minutes' => 45,
            'image_url' => 'javascript:alert(1)',
            'status' => 'ativa',
            'capacity' => 0,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => 'Já existe uma área comum com este nome.',
                'closing_time' => 'O horário de fechamento deve ser posterior ao de abertura, no mesmo dia. Para fechar à meia-noite, use 24:00.',
                'max_reservation_minutes' => 'A duração máxima deve ser múltipla de 30 minutos, o tamanho dos blocos de reserva.',
                'image_url' => 'Informe o endereço da imagem começando por http:// ou https://.',
                'status' => 'A situação deve ser available (disponível) ou unavailable (indisponível).',
                'capacity' => 'A capacidade deve ser de pelo menos 1 pessoa.',
            ]);

        $this->actingAs($admin)->postJson('/api/common-areas', $this->formulario(['opening_time' => '8h', 'max_reservation_minutes' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'opening_time' => 'Informe o horário de abertura no formato HH:MM.',
                'max_reservation_minutes' => 'A duração máxima deve ser de pelo menos 30 minutos.',
            ]);

        $this->assertDatabaseCount('common_areas', 1);
    }

    public function test_edicao_parcial(): void
    {
        $area = $this->area(['name' => 'Quadra', 'capacity' => 20]);

        $this->actingAs($this->admin())->patchJson("/api/common-areas/{$area->id}", [
            'name' => 'Quadra poliesportiva',
            'requires_approval' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', "Área comum {$area->id} Quadra poliesportiva editada.")
            ->assertJsonPath('data.name', 'Quadra poliesportiva')
            ->assertJsonPath('data.requires_approval', true)
            ->assertJsonPath('data.capacity', 20)
            ->assertJsonPath('data.opening_time', '08:00');

        $this->assertSame(1, Log::where('type_log_id', TypeLogEnum::COMMON_AREA->value)->count());
    }

    public function test_o_nome_continua_unico_na_edicao(): void
    {
        $admin = $this->admin();
        $quadra = $this->area(['name' => 'Quadra']);
        $this->area(['name' => 'Piscina']);

        $this->actingAs($admin)->patchJson("/api/common-areas/{$quadra->id}", ['name' => 'Piscina'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Já existe uma área comum com este nome.']);

        // O próprio nome não conta como repetido.
        $this->actingAs($admin)->patchJson("/api/common-areas/{$quadra->id}", ['name' => 'Quadra'])->assertStatus(201);
    }

    public function test_os_horarios_mudam_mas_nao_somem_nem_se_invertem(): void
    {
        $admin = $this->admin();
        $area = $this->area();

        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['opening_time' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time' => 'Informe o horário de abertura.']);

        // Só o fechamento, antes da abertura gravada (08:00:00).
        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['closing_time' => '07:00'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O horário de fechamento deve ser posterior ao de abertura, no mesmo dia. Para fechar à meia-noite, use 24:00.');

        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['opening_time' => '23:00', 'closing_time' => '22:00'])
            ->assertStatus(422);

        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['opening_time' => '07:00', 'closing_time' => '23:00'])
            ->assertStatus(201)
            ->assertJsonPath('data.opening_time', '07:00')
            ->assertJsonPath('data.closing_time', '23:00');

        $this->assertSame('07:00', $area->refresh()->opening_time);
    }

    public function test_tornar_indisponivel_avisa_das_reservas_futuras(): void
    {
        $area = $this->area(['name' => 'Quadra']);
        $this->reserva($area, '2026-11-24', '10:00:00', '11:00:00');
        $this->reserva($area, '2026-11-30', null, null, ReservationStatus::Pending);
        // Não contam: já passou, foi cancelada ou foi recusada.
        $this->reserva($area, '2026-11-20', '10:00:00', '11:00:00');
        $this->reserva($area, '2026-11-25', '10:00:00', '11:00:00', ReservationStatus::Cancelled);
        $this->reserva($area, '2026-11-26', '10:00:00', '11:00:00', ReservationStatus::Rejected);

        $this->actingAs($this->admin())->patchJson("/api/common-areas/{$area->id}", ['status' => 'unavailable'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath(
                'message',
                "Área comum {$area->id} Quadra editada. 2 reservas futuras pendentes ou aprovadas não se encaixam mais nas regras da área: reveja-as na agenda de reservas."
            );

        // As reservas continuam como estavam: decidir sobre elas é da administração.
        $this->assertSame(2, Reservation::whereIn('status', ReservationStatus::blocking())->where('date', '>=', self::HOJE)->count());
    }

    public function test_a_area_indisponivel_sai_do_calendario_e_volta_quando_reativada(): void
    {
        $admin = $this->admin();
        $area = $this->area();
        $morador = User::factory()->resident()->create();
        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);
        $reservar = fn () => $this->actingAs($morador)->postJson('/api/reservations', [
            'common_area_id' => $area->id, 'date' => '2026-11-24', 'start_time' => '10:00', 'end_time' => '11:00',
        ]);

        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['status' => 'unavailable'])->assertStatus(201);

        $this->actingAs($morador)
            ->getJson("/api/common-areas/{$area->id}/availability?start_date=2026-11-24&end_date=2026-11-24")
            ->assertJsonPath('data.days.0.reason', 'area_unavailable');
        $reservar()->assertStatus(422);

        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['status' => 'available'])
            ->assertStatus(201)
            ->assertJsonPath('message', "Área comum {$area->id} {$area->name} editada.");
        $reservar()->assertStatus(201);
    }

    public function test_mudar_horario_ou_duracao_avisa_so_das_reservas_que_nao_cabem_mais(): void
    {
        $admin = $this->admin();
        $area = $this->area(['name' => 'Quadra']);
        $this->reserva($area, '2026-11-24', '08:00:00', '09:00:00');
        $this->reserva($area, '2026-11-24', '12:00:00', '14:00:00');
        $this->reserva($area, '2026-11-25', null, null);

        // Abrir às 9h deixa de fora só a das 8h; a diária não depende do horário.
        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['opening_time' => '09:00'])
            ->assertStatus(201)
            ->assertJsonPath('message', "Área comum {$area->id} Quadra editada. 1 reserva futura pendente ou aprovada não se encaixa mais nas regras da área: reveja-as na agenda de reservas.");

        // Uma hora de duração máxima deixa de fora também a de duas horas.
        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['max_reservation_minutes' => 60])
            ->assertStatus(201)
            ->assertJsonPath('message', "Área comum {$area->id} Quadra editada. 2 reservas futuras pendentes ou aprovadas não se encaixam mais nas regras da área: reveja-as na agenda de reservas.");

        // Editar o que não é regra (aqui, a descrição) não repete o aviso.
        $this->actingAs($admin)->patchJson("/api/common-areas/{$area->id}", ['description' => 'Quadra coberta.'])
            ->assertStatus(201)
            ->assertJsonPath('message', "Área comum {$area->id} Quadra editada.");
    }

    public function test_excluir_area_sem_reservas(): void
    {
        $area = $this->area(['name' => 'Sauna']);

        $this->actingAs($this->admin())->deleteJson("/api/common-areas/{$area->id}")
            ->assertStatus(201)
            ->assertExactJson(['status' => true]);

        $this->assertDatabaseMissing('common_areas', ['id' => $area->id]);

        $log = Log::where('type_log_id', TypeLogEnum::COMMON_AREA->value)->sole();
        $this->assertSame("Área comum {$area->id} Sauna excluída.", $log->description);
        $this->assertSame('Sauna', json_decode($log->getRawOriginal('data_log'), true)['name']);
    }

    public function test_area_com_reservas_nao_e_excluida(): void
    {
        $area = $this->area(['name' => 'Quadra']);
        // Até uma cancelada é histórico: a exclusão a apagaria em cascata.
        $this->reserva($area, '2026-10-10', '10:00:00', '11:00:00', ReservationStatus::Cancelled);

        $this->actingAs($this->admin())->deleteJson("/api/common-areas/{$area->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'A área comum Quadra tem 1 reserva no histórico: torne-a indisponível em vez de excluí-la, para não apagar as reservas.');

        $this->assertDatabaseHas('common_areas', ['id' => $area->id]);
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_so_a_administracao_escreve(): void
    {
        $area = $this->area();

        foreach ([User::factory()->resident()->create(), User::factory()->syndic()->create(), User::factory()->employee()->create()] as $usuario) {
            $this->actingAs($usuario)->postJson('/api/common-areas', $this->formulario())->assertStatus(403);
            $this->actingAs($usuario)->patchJson("/api/common-areas/{$area->id}", ['name' => 'Outra'])->assertStatus(403);
            $this->actingAs($usuario)->deleteJson("/api/common-areas/{$area->id}")->assertStatus(403);
        }

        $this->assertDatabaseCount('common_areas', 1);
        $this->assertDatabaseHas('common_areas', ['id' => $area->id, 'name' => $area->name]);
    }

    public function test_sindico_e_funcionario_nao_consultam_sem_o_caso_de_uso(): void
    {
        $this->actingAs(User::factory()->syndic()->create())->getJson('/api/common-areas')->assertStatus(403);
        $this->actingAs(User::factory()->employee()->create())->getJson('/api/common-areas')->assertStatus(403);
        $this->actingAs($this->admin())->getJson('/api/common-areas')->assertStatus(201);
    }

    public function test_sem_sessao_responde_401(): void
    {
        $this->getJson('/api/common-areas')->assertStatus(401);
        $this->postJson('/api/common-areas', $this->formulario())->assertStatus(401);
    }

    public function test_inexistente_responde_404_na_edicao_e_na_exclusao(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson('/api/common-areas/999', ['name' => 'Nada'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'Área comum 999 não encontrada.');

        $this->actingAs($admin)->deleteJson('/api/common-areas/999')->assertStatus(404);
    }
}
