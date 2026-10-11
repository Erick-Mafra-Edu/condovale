<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Middleware unit.linked — "esta pessoa está associada a uma unidade ou ao
 * condomínio?".
 *
 * A rota de teste é registrada aqui porque o grupo unit.linked ainda está
 * vazio: os módulos de reserva e ocorrência, que são os que exigem vínculo,
 * chegam nas tasks seguintes. Testar contra uma rota própria mede o
 * middleware, e não o módulo que por acaso o usa.
 */
class UnitLinkMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth', 'user.active', 'unit.linked'])
            ->get('/api/teste-vinculo', fn () => response()->json(['ok' => true]));
    }

    private function vincular(User $user): void
    {
        UnitOccupancy::factory()->owner()->create([
            'unit_id' => Unit::factory()->create()->id,
            'user_id' => $user->id,
            'started_at' => now()->toDateString(),
        ]);
    }

    public function test_barra_morador_sem_vinculo(): void
    {
        $morador = User::factory()->resident()->create();

        $this->actingAs($morador)
            ->getJson('/api/teste-vinculo')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Seu usuário ainda não está vinculado a uma unidade. Procure a administração do condomínio.');
    }

    public function test_libera_morador_vinculado(): void
    {
        $morador = User::factory()->resident()->create();
        $this->vincular($morador);

        $this->actingAs($morador)
            ->getJson('/api/teste-vinculo')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_barra_morador_cujo_vinculo_foi_encerrado(): void
    {
        $morador = User::factory()->resident()->create();
        $this->vincular($morador);

        $morador->unitOccupancies()->update(['is_active' => false, 'ended_at' => now()->toDateString()]);

        $this->actingAs($morador)
            ->getJson('/api/teste-vinculo')
            ->assertStatus(403);
    }

    public function test_libera_quem_responde_pelo_condominio_inteiro(): void
    {
        // Síndico, administrador e funcionário não ocupam unidade: exigir
        // vínculo deles travaria a administração do sistema.
        foreach ([User::factory()->syndic(), User::factory()->admin(), User::factory()->employee()] as $papel) {
            $this->actingAs($papel->create())
                ->getJson('/api/teste-vinculo')
                ->assertStatus(200);
        }
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/teste-vinculo')->assertStatus(401);
    }
}
