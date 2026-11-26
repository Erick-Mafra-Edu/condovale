<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\NoticeStatus;
use App\Models\Log;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * RF05 — `PATCH /notices/{id}`, a edição que o contrato do frontend já tinha.
 *
 * Corrigir um comunicado não apaga o que estava escrito: o texto anterior fica
 * na auditoria. Autor e data de publicação não mudam.
 */
class NoticeEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-24 10:00:00'));
    }

    private function sindico(): User
    {
        return User::factory()->syndic()->create(['name' => 'Carlos Síndico']);
    }

    public function test_corrige_o_comunicado_publicado_e_guarda_o_texto_anterior(): void
    {
        $autor = $this->sindico();
        $comunicado = Notice::factory()->create([
            'author_id' => $autor->id,
            'title' => 'Assembleia geral',
            'content' => 'Dia 25, às 19h.',
            'published_at' => '2026-11-20 08:00:00',
        ]);
        $admin = User::factory()->admin()->create(['name' => 'Ana Admin']);

        $this->actingAs($admin)
            ->patchJson("/api/notices/{$comunicado->id}", [
                'content' => 'Dia 26, às 19h30.',
                // Ignorados: autor e data são do servidor.
                'author_id' => $admin->id,
                'published_at' => '2020-01-01 00:00:00',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', "Comunicado {$comunicado->id} atualizado.")
            ->assertJsonPath('data.content', 'Dia 26, às 19h30.')
            ->assertJsonPath('data.title', 'Assembleia geral')
            ->assertJsonPath('data.author', ['id' => $autor->id, 'name' => 'Carlos Síndico']);

        $comunicado->refresh();
        $this->assertSame($autor->id, $comunicado->author_id);
        $this->assertSame('2026-11-20 08:00:00', $comunicado->published_at->toDateTimeString());

        $log = Log::where('action', AuditAction::NoticeUpdated->value)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($comunicado->id, $log->subject_id);
        $this->assertSame("Comunicado {$comunicado->id} Assembleia geral editado por Ana Admin.", $log->description);
        $this->assertSame(['de' => ['content' => 'Dia 25, às 19h.'], 'para' => ['content' => 'Dia 26, às 19h30.']], $log->data_log);
    }

    public function test_status_publicado_publica_o_rascunho(): void
    {
        $rascunho = Notice::factory()->draft()->create(['title' => 'Rascunho']);

        $this->actingAs($this->sindico())
            ->patchJson("/api/notices/{$rascunho->id}", ['title' => 'Obras na garagem', 'status' => 'published'])
            ->assertStatus(201)
            ->assertJsonPath('message', "Comunicado {$rascunho->id} atualizado e publicado.")
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.title', 'Obras na garagem');

        $this->assertSame('2026-11-24 10:00:00', $rascunho->refresh()->published_at->toDateTimeString());
        $this->assertSame(1, Log::where('action', AuditAction::NoticeUpdated->value)->count());
        $this->assertSame(1, Log::where('action', AuditAction::NoticePublished->value)->count());
    }

    public function test_publicado_nao_volta_a_rascunho(): void
    {
        $comunicado = Notice::factory()->create(['title' => 'Aviso']);

        $this->actingAs($this->sindico())
            ->patchJson("/api/notices/{$comunicado->id}", ['title' => 'Aviso corrigido', 'status' => 'draft'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Um comunicado publicado não volta a rascunho: exclua-o se ele não vale mais.');

        $comunicado->refresh();
        $this->assertSame(NoticeStatus::Published, $comunicado->status);
        $this->assertSame('Aviso', $comunicado->title);
        $this->assertSame(0, Log::count());
    }

    public function test_reenviar_o_mesmo_texto_nao_vira_registro(): void
    {
        $comunicado = Notice::factory()->create(['title' => 'Aviso', 'content' => 'Texto.']);

        $this->actingAs($this->sindico())
            ->patchJson("/api/notices/{$comunicado->id}", ['title' => 'Aviso', 'content' => 'Texto.', 'status' => 'published'])
            ->assertStatus(201);

        $this->assertSame(0, Log::count());
    }

    public function test_campos_vazios_ou_longos_sao_recusados(): void
    {
        $comunicado = Notice::factory()->create();

        $this->actingAs($this->sindico())
            ->patchJson("/api/notices/{$comunicado->id}", ['title' => '', 'content' => str_repeat('a', 5001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'title' => 'O título do comunicado não pode ficar vazio.',
                'content' => 'O conteúdo do comunicado deve ter no máximo 5000 caracteres.',
            ]);
    }

    public function test_so_quem_publica_edita(): void
    {
        $comunicado = Notice::factory()->create(['title' => 'Aviso']);

        foreach (['resident', 'employee'] as $perfil) {
            $this->actingAs(User::factory()->{$perfil}()->create())
                ->patchJson("/api/notices/{$comunicado->id}", ['title' => 'Invadido'])
                ->assertStatus(403);
        }

        $this->assertSame('Aviso', $comunicado->refresh()->title);

        $this->actingAs($this->sindico())
            ->patchJson('/api/notices/999999', ['title' => 'Nada'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'Comunicado 999999 não encontrado.');
    }
}
