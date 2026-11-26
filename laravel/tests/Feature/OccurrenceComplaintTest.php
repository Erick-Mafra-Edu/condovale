<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\OccurrenceKind;
use App\Models\Log;
use App\Models\Occurrence;
use App\Models\OccurrenceType;
use App\Models\UnitOccupancy;
use App\Models\User;
use Database\Seeders\OccurrenceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * RF04 — "UC7 - Registrar denúncia", ao lado do "UC6 - Registrar ocorrência".
 *
 * A denúncia é um chamado como os outros — do morador que abriu, com status
 * (RN05), tratado pelo mesmo fluxo (RN06) —, cuja natureza vem do tipo. A
 * administração decide quais tipos são denúncia; a listagem e o relatório
 * separam as duas naturezas.
 */
class OccurrenceComplaintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-24 10:00:00'));
    }

    private function morador(): User
    {
        $morador = User::factory()->resident()->create();
        UnitOccupancy::factory()->owner()->create(['user_id' => $morador->id]);

        return $morador->refresh();
    }

    public function test_o_tipo_nasce_como_manutencao_e_pode_ser_denuncia(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/occurrence-types', ['name' => 'Elevador'])
            ->assertStatus(201)
            ->assertJsonPath('data.kind', 'maintenance');

        $id = $this->actingAs($admin)->postJson('/api/occurrence-types', ['name' => 'Animais', 'kind' => 'complaint'])
            ->assertStatus(201)
            ->assertJsonPath('data.kind', 'complaint')
            ->json('data.id');

        $this->actingAs($admin)->postJson('/api/occurrence-types', ['name' => 'Outro', 'kind' => 'fofoca'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kind' => 'Natureza inválida. Use manutenção (maintenance) ou denúncia (complaint).']);

        $this->actingAs($admin)->patchJson("/api/occurrence-types/{$id}", ['kind' => 'maintenance'])
            ->assertStatus(201)
            ->assertJsonPath('data.kind', 'maintenance');

        $log = Log::where('action', AuditAction::OccurrenceTypeUpdated->value)->sole();
        $this->assertSame('complaint', $log->data_log['de']['kind']);
        $this->assertSame('maintenance', $log->data_log['para']['kind']);
    }

    public function test_o_morador_registra_uma_denuncia(): void
    {
        $morador = $this->morador();
        $barulho = OccurrenceType::factory()->complaint()->create(['name' => 'Barulho e sossego']);

        $id = $this->actingAs($morador)->postJson('/api/occurrences', [
            'title' => 'Som alto depois das 22h',
            'description' => 'Festa no 302 com som alto toda sexta.',
            'occurrence_type_id' => $barulho->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.resident_id', $morador->id)
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        // O mesmo fluxo do chamado de manutenção, com a natureza à vista.
        $this->actingAs($morador)->getJson("/api/occurrences/{$id}")
            ->assertStatus(201)
            ->assertJsonPath('data.occurrence_type.kind', 'complaint');
    }

    public function test_a_listagem_separa_denuncias_de_manutencao(): void
    {
        $morador = $this->morador();
        $manutencao = OccurrenceType::factory()->create();
        $denuncia = OccurrenceType::factory()->complaint()->create();

        Occurrence::factory()->count(2)->create(['resident_id' => $morador->id, 'occurrence_type_id' => $manutencao->id]);
        Occurrence::factory()->create(['resident_id' => $morador->id, 'occurrence_type_id' => $denuncia->id, 'title' => 'Denúncia']);
        // De outro morador: o recorte da sessão continua valendo com o filtro.
        Occurrence::factory()->create(['occurrence_type_id' => $denuncia->id]);

        $this->actingAs($morador)->getJson('/api/occurrences?kind=complaint')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Denúncia')
            ->assertJsonPath('data.0.occurrence_type.kind', 'complaint');

        $this->actingAs($morador)->getJson('/api/occurrences?kind=maintenance')->assertJsonPath('total', 2);
        $this->actingAs($morador)->getJson('/api/occurrences')->assertJsonPath('total', 3);

        $this->actingAs(User::factory()->admin()->create())->getJson('/api/occurrences?kind=complaint')->assertJsonPath('total', 2);

        $this->actingAs($morador)->getJson('/api/occurrences?kind=outra')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kind']);
    }

    public function test_o_relatorio_conta_e_filtra_por_natureza(): void
    {
        $manutencao = OccurrenceType::factory()->create();
        $denuncia = OccurrenceType::factory()->complaint()->create();

        Occurrence::factory()->count(3)->create(['occurrence_type_id' => $manutencao->id]);
        Occurrence::factory()->create(['occurrence_type_id' => $denuncia->id]);

        $url = '/api/reports/occurrences?from=2026-11-01&to=2026-11-30';
        $sindico = User::factory()->syndic()->create();

        $this->actingAs($sindico)->getJson($url)
            ->assertStatus(201)
            ->assertJsonPath('data.by_kind', [
                ['kind' => 'maintenance', 'label' => 'Manutenção', 'total' => 3],
                ['kind' => 'complaint', 'label' => 'Denúncia', 'total' => 1],
            ]);

        // O filtro recorta a lista, não os indicadores.
        $this->actingAs($sindico)->getJson($url.'&kind=complaint')
            ->assertStatus(201)
            ->assertJsonPath('data.occurrences.total', 1)
            ->assertJsonPath('data.occurrences.data.0.occurrence_type.kind', 'complaint')
            ->assertJsonPath('data.summary.total', 4);
    }

    public function test_a_carga_traz_tipos_de_denuncia_e_pode_ser_reaplicada(): void
    {
        $this->seed(OccurrenceTypeSeeder::class);
        $antes = OccurrenceType::count();
        $this->seed(OccurrenceTypeSeeder::class);

        $this->assertSame($antes, OccurrenceType::count());
        $this->assertSame(
            ['Barulho e sossego', 'Conduta e regimento interno'],
            OccurrenceType::where('kind', OccurrenceKind::Complaint)->orderBy('name')->pluck('name')->all()
        );
    }
}
