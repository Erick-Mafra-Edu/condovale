<?php

namespace Tests\Feature;

use App\Enums\NoticeStatus;
use App\Enums\TypeLogEnum;
use App\Models\Log;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC05 / RF05 — publicação, consulta e exclusão de comunicados oficiais.
 *
 * RN07: só síndico e administração publicam. RN03: só eles excluem. RN18: quem
 * lê vê apenas o publicado, do mais recente para o mais antigo.
 */
class NoticeTest extends TestCase
{
    use RefreshDatabase;

    private function comunicado(array $attributes = []): Notice
    {
        return Notice::factory()->create($attributes);
    }

    private function rascunho(array $attributes = []): Notice
    {
        return Notice::factory()->draft()->create($attributes);
    }

    public function test_o_morador_ve_so_os_publicados_do_mais_recente_para_o_mais_antigo(): void
    {
        $antigo = $this->comunicado(['published_at' => now()->subDays(5)]);
        $recente = $this->comunicado(['published_at' => now()->subDay()]);
        $this->rascunho();

        $this->actingAs(User::factory()->resident()->create())
            ->getJson('/api/notices')
            ->assertStatus(201)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.1.id', $antigo->id);
    }

    public function test_sindico_e_administracao_veem_tambem_os_rascunhos(): void
    {
        $this->comunicado();
        $rascunho = $this->rascunho();

        foreach ([User::factory()->syndic()->create(), User::factory()->admin()->create()] as $gestor) {
            $resposta = $this->actingAs($gestor)->getJson('/api/notices')
                ->assertStatus(201)
                ->assertJsonPath('total', 2);

            $this->assertContains($rascunho->id, array_column($resposta->json('data'), 'id'));
        }
    }

    public function test_a_listagem_aceita_busca_e_traz_o_autor_so_com_id_e_nome(): void
    {
        $sindico = User::factory()->syndic()->create(['name' => 'Carlos Síndico']);
        $this->comunicado(['title' => 'Manutenção dos elevadores', 'author_id' => $sindico->id]);
        $this->comunicado(['title' => 'Assembleia geral']);

        // `relations` é descartado: por ele sairia o cadastro inteiro do autor.
        $resposta = $this->actingAs(User::factory()->resident()->create())
            ->getJson('/api/notices?search=elevadores&relations=author')
            ->assertStatus(201)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.author', ['id' => $sindico->id, 'name' => 'Carlos Síndico']);

        $this->assertStringNotContainsString('@', json_encode($resposta->json('data')));
    }

    public function test_funcionario_le_o_mural_so_com_concessao_individual(): void
    {
        $funcionario = User::factory()->employee()->create();
        $this->comunicado();

        $this->actingAs($funcionario)->getJson('/api/notices')->assertStatus(403);

        $funcionario->givePermissionTo('view-notices');

        $this->actingAs($funcionario)->getJson('/api/notices')
            ->assertStatus(201)
            ->assertJsonPath('total', 1);
    }

    public function test_detalhe_de_um_comunicado_publicado(): void
    {
        $comunicado = $this->comunicado(['title' => 'Assembleia geral']);

        $this->actingAs(User::factory()->resident()->create())
            ->getJson("/api/notices/{$comunicado->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Assembleia geral')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.author.id', $comunicado->author_id);
    }

    public function test_rascunho_responde_404_para_quem_so_le(): void
    {
        // A mesma resposta de um comunicado que não existe: a diferença
        // revelaria que há um aviso em preparação.
        $rascunho = $this->rascunho();
        $morador = User::factory()->resident()->create();

        $this->actingAs($morador)->getJson("/api/notices/{$rascunho->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', "Comunicado {$rascunho->id} não encontrado.");

        $this->actingAs($morador)->getJson('/api/notices/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Comunicado 999 não encontrado.');

        $this->actingAs(User::factory()->syndic()->create())
            ->getJson("/api/notices/{$rascunho->id}")
            ->assertStatus(201);
    }

    public function test_sindico_e_administracao_publicam(): void
    {
        foreach ([User::factory()->syndic()->create(), User::factory()->admin()->create()] as $gestor) {
            $this->actingAs($gestor)->postJson('/api/notices', [
                'title' => 'Manutenção dos elevadores',
                'content' => 'Os elevadores do Bloco A param das 8h às 12h.',
            ])
                ->assertStatus(201)
                ->assertJsonPath('message', 'Comunicado '.Notice::latest('id')->value('id').' publicado.')
                ->assertJsonPath('data.status', 'published')
                ->assertJsonPath('data.author_id', $gestor->id)
                ->assertJsonPath('data.author.name', $gestor->name);
        }

        $this->assertSame(2, Notice::where('status', NoticeStatus::Published->value)->whereNotNull('published_at')->count());
    }

    public function test_autor_e_data_vem_do_servidor_e_nao_do_corpo(): void
    {
        $sindico = User::factory()->syndic()->create();
        $outro = User::factory()->admin()->create();

        $this->actingAs($sindico)->postJson('/api/notices', [
            'title' => 'Aviso',
            'content' => 'Conteúdo do aviso.',
            'author_id' => $outro->id,
            'authorId' => $outro->id,
            'published_at' => '2020-01-01 10:00:00',
            'publishedAt' => '2020-01-01T10:00:00Z',
        ])->assertStatus(201);

        $comunicado = Notice::firstOrFail();

        $this->assertSame($sindico->id, $comunicado->author_id);
        $this->assertTrue($comunicado->published_at->isToday());
    }

    public function test_salva_como_rascunho_sem_data_de_publicacao(): void
    {
        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/notices', ['title' => 'Regulamento da piscina', 'content' => 'Minuta em revisão.', 'status' => 'draft'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $this->assertStringEndsWith('salvo como rascunho.', $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/notices', ['title' => 'Outro', 'content' => 'Texto.', 'status' => 'draft'])
            ->json('message'));
    }

    public function test_a_publicacao_e_registrada_na_auditoria(): void
    {
        $sindico = User::factory()->syndic()->create(['name' => 'Carlos Síndico']);

        $id = $this->actingAs($sindico)
            ->postJson('/api/notices', ['title' => 'Assembleia geral', 'content' => 'Dia 25, às 19h30.'])
            ->json('data.id');

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::NOTICE->value,
            'user_id' => $sindico->id,
            'description' => "Comunicado {$id} Assembleia geral publicado por Carlos Síndico.",
        ]);
    }

    public function test_morador_e_funcionario_nao_publicam(): void
    {
        $funcionario = User::factory()->employee()->create();
        $funcionario->givePermissionTo('view-notices');

        foreach ([User::factory()->resident()->create(), $funcionario] as $usuario) {
            $this->actingAs($usuario)
                ->postJson('/api/notices', ['title' => 'Aviso', 'content' => 'Texto.'])
                ->assertStatus(403);
        }

        $this->assertSame(0, Notice::count());
    }

    public function test_valida_o_comunicado(): void
    {
        $sindico = User::factory()->syndic()->create();

        $this->actingAs($sindico)->postJson('/api/notices', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'Informe o título do comunicado.')
            ->assertJsonPath('errors.content.0', 'Informe o conteúdo do comunicado.');

        $this->actingAs($sindico)->postJson('/api/notices', [
            'title' => str_repeat('a', 121),
            'content' => 'Texto.',
            'status' => 'arquivado',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', 'O título do comunicado deve ter no máximo 120 caracteres.')
            ->assertJsonPath('errors.status.0', 'Situação inválida. Use rascunho ou publicado.');
    }

    public function test_publicar_um_rascunho(): void
    {
        $rascunho = $this->rascunho(['title' => 'Regulamento da piscina']);
        $admin = User::factory()->admin()->create(['name' => 'Ana Administração']);

        $this->actingAs($admin)->postJson("/api/notices/{$rascunho->id}/publish")
            ->assertStatus(201)
            ->assertJsonPath('message', "Comunicado {$rascunho->id} publicado.")
            ->assertJsonPath('data.status', 'published');

        $rascunho->refresh();

        $this->assertSame(NoticeStatus::Published, $rascunho->status);
        $this->assertTrue($rascunho->published_at->isToday());
        // O autor continua sendo quem escreveu; quem publicou fica na auditoria.
        $this->assertNotSame($admin->id, $rascunho->author_id);
        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::NOTICE->value,
            'description' => "Comunicado {$rascunho->id} Regulamento da piscina publicado por Ana Administração.",
        ]);

        // O morador passa a ver.
        $this->actingAs(User::factory()->resident()->create())
            ->getJson("/api/notices/{$rascunho->id}")
            ->assertStatus(201);
    }

    public function test_nao_publica_duas_vezes(): void
    {
        $comunicado = $this->comunicado(['published_at' => now()->subDays(3)]);

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson("/api/notices/{$comunicado->id}/publish")
            ->assertStatus(409)
            ->assertJsonPath('message', "O comunicado {$comunicado->id} já está publicado.");

        // A data original de publicação não muda.
        $this->assertTrue($comunicado->fresh()->published_at->isSameDay(now()->subDays(3)));
    }

    public function test_publicar_exige_permissao_e_comunicado_existente(): void
    {
        $rascunho = $this->rascunho();

        $this->actingAs(User::factory()->resident()->create())
            ->postJson("/api/notices/{$rascunho->id}/publish")
            ->assertStatus(403);

        $this->actingAs(User::factory()->syndic()->create())
            ->postJson('/api/notices/999/publish')
            ->assertStatus(404);
    }

    public function test_sindico_e_administracao_excluem_qualquer_comunicado(): void
    {
        // RN03: a exclusão é do síndico e da administração, não só do autor.
        $doAdmin = $this->comunicado(['author_id' => User::factory()->admin()->create()->id]);
        $doSindico = $this->comunicado(['author_id' => User::factory()->syndic()->create()->id]);

        $this->actingAs(User::factory()->syndic()->create())
            ->deleteJson("/api/notices/{$doAdmin->id}")
            ->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/notices/{$doSindico->id}")
            ->assertStatus(201);

        $this->assertSame(0, Notice::count());
    }

    public function test_a_exclusao_guarda_o_comunicado_na_auditoria(): void
    {
        $comunicado = $this->comunicado(['title' => 'Assembleia geral', 'content' => 'Dia 25, às 19h30.']);
        $sindico = User::factory()->syndic()->create(['name' => 'Carlos Síndico']);

        $this->actingAs($sindico)->deleteJson("/api/notices/{$comunicado->id}")->assertStatus(201);

        $log = Log::where('description', "Comunicado {$comunicado->id} Assembleia geral excluído por Carlos Síndico.")->firstOrFail();

        $this->assertSame(TypeLogEnum::NOTICE->value, $log->type_log_id);
        $this->assertSame('Assembleia geral', $log->data_log['title']);
        $this->assertSame('Dia 25, às 19h30.', $log->data_log['content']);
        $this->assertSame($comunicado->author_id, $log->data_log['author_id']);
    }

    public function test_morador_nao_exclui_e_comunicado_inexistente_responde_404(): void
    {
        $comunicado = $this->comunicado();

        $this->actingAs(User::factory()->resident()->create())
            ->deleteJson("/api/notices/{$comunicado->id}")
            ->assertStatus(403);

        $this->actingAs(User::factory()->syndic()->create())
            ->deleteJson('/api/notices/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Comunicado 999 não encontrado.');

        $this->assertSame(1, Notice::count());
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/notices')->assertStatus(401);
        $this->getJson('/api/notices/1')->assertStatus(401);
        $this->postJson('/api/notices', [])->assertStatus(401);
        $this->postJson('/api/notices/1/publish')->assertStatus(401);
        $this->deleteJson('/api/notices/1')->assertStatus(401);
    }
}
