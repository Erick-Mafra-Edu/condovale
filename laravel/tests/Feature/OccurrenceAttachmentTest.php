<?php

namespace Tests\Feature;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Enums\TypeLogEnum;
use App\Models\Occurrence;
use App\Models\OccurrenceAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * RF04 — imagens anexadas ao chamado.
 *
 * A configuração é fixada no setUp, abaixo de qualquer php.ini: o teto real é
 * o menor entre o configurado e o do servidor, e o teste não pode depender de
 * quanto a máquina de quem o roda aceita.
 */
class OccurrenceAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private const DISCO = 'occurrence_attachments';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(self::DISCO);

        config([
            'uploads.occurrence_attachments.max_kilobytes' => 1024,
            'uploads.occurrence_attachments.max_per_occurrence' => 3,
            'uploads.occurrence_attachments.max_dimension' => 800,
            'uploads.occurrence_attachments.max_megapixels' => 4,
            'uploads.occurrence_attachments.reencode' => true,
        ]);
    }

    private function chamadoDe(User $morador, array $attributes = []): Occurrence
    {
        return Occurrence::factory()->create(array_merge(['resident_id' => $morador->id], $attributes));
    }

    private function enviar(User $autor, Occurrence $chamado, UploadedFile $arquivo)
    {
        return $this->actingAs($autor)->post(
            "/api/occurrences/{$chamado->id}/attachments",
            ['image' => $arquivo],
            ['Accept' => 'application/json']
        );
    }

    private function gravado(int $attachmentId): string
    {
        return Storage::disk(self::DISCO)->get(OccurrenceAttachment::findOrFail($attachmentId)->path);
    }

    /**
     * JPEG com a orientação gravada no EXIF, como sai de um celular.
     */
    private function fotoDeCelular(int $largura, int $altura, int $orientacao): UploadedFile
    {
        $imagem = imagecreatetruecolor($largura, $altura);
        ob_start();
        imagejpeg($imagem, null, 90);
        $jpeg = (string) ob_get_clean();

        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 1)
            .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientacao).pack('v', 0)
            .pack('V', 0);
        $app1 = "Exif\0\0".$tiff;
        $segmento = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        return UploadedFile::fake()->createWithContent('foto.jpg', substr($jpeg, 0, 2).$segmento.substr($jpeg, 2));
    }

    public function test_o_morador_anexa_imagem_ao_proprio_chamado(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $resposta = $this->enviar($morador, $chamado, UploadedFile::fake()->image('vazamento.jpg', 640, 480))
            ->assertStatus(201)
            ->assertJsonPath('data.occurrence_id', $chamado->id)
            ->assertJsonPath('data.original_name', 'vazamento.jpg')
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.width', 640)
            ->assertJsonPath('data.height', 480)
            ->assertJsonMissingPath('data.path');

        $this->assertStringEndsWith(
            "/api/occurrences/{$chamado->id}/attachments/{$resposta->json('data.id')}",
            $resposta->json('data.url')
        );

        Storage::disk(self::DISCO)->assertExists(OccurrenceAttachment::first()->path);
    }

    public function test_o_arquivo_e_gravado_com_nome_aleatorio_dentro_da_pasta_do_chamado(): void
    {
        // O nome enviado pode trazer ../, colidir com outro arquivo ou revelar
        // o que o morador não quis mostrar. Ele fica só para exibição.
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->image('../../../.env.jpg', 100, 100))
            ->assertStatus(201)
            ->assertJsonPath('data.original_name', '.env.jpg');

        $this->assertMatchesRegularExpression(
            "#^{$chamado->id}/[0-9a-f-]{36}\\.jpg$#",
            OccurrenceAttachment::first()->path
        );
    }

    public function test_png_e_regravado_como_jpeg(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->image('print.png', 300, 200))
            ->assertStatus(201)
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.original_name', 'print.jpg');

        $conteudo = $this->gravado(OccurrenceAttachment::first()->id);
        $this->assertSame("\xFF\xD8", substr($conteudo, 0, 2), 'O arquivo gravado não é JPEG.');
    }

    public function test_foto_grande_e_reduzida_mantendo_a_proporcao(): void
    {
        // A foto de celular sai de alguns MB para algumas centenas de KB: é o
        // que cabe nos 5 GB do plano gratuito.
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->image('grande.jpg', 1600, 1200))
            ->assertStatus(201)
            ->assertJsonPath('data.width', 800)
            ->assertJsonPath('data.height', 600);
    }

    public function test_foto_tirada_em_pe_fica_em_pe(): void
    {
        // O celular grava a foto deitada e anota no EXIF que ela deve ser
        // girada. A regravação descarta o EXIF, então a rotação precisa ir
        // para os pixels — senão a foto apareceria de lado.
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, $this->fotoDeCelular(200, 100, 6))
            ->assertStatus(201)
            ->assertJsonPath('data.width', 100)
            ->assertJsonPath('data.height', 200);
    }

    public function test_os_metadados_da_camera_nao_sao_guardados(): void
    {
        // O EXIF pode trazer as coordenadas de onde a foto foi tirada.
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, $this->fotoDeCelular(200, 100, 6))->assertStatus(201);

        $temporario = tempnam(sys_get_temp_dir(), 'anexo');
        file_put_contents($temporario, $this->gravado(OccurrenceAttachment::first()->id));
        $exif = @exif_read_data($temporario);
        unlink($temporario);

        $this->assertArrayNotHasKey('Orientation', is_array($exif) ? $exif : []);
    }

    public function test_sem_regravacao_guarda_o_original_ja_conferido(): void
    {
        // O caminho de um servidor sem GD: perde-se a redução, mas o morador
        // não fica sem anexar, e o tipo continua conferido pelos bytes.
        config(['uploads.occurrence_attachments.reencode' => false]);

        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);
        $png = UploadedFile::fake()->image('print.png', 1600, 1200);

        $this->enviar($morador, $chamado, $png)
            ->assertStatus(201)
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.original_name', 'print.png')
            ->assertJsonPath('data.width', 1600);
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado_pelos_bytes(): void
    {
        // A extensão é .jpg, mas o conteúdo não é imagem: quem decide são os
        // bytes, não o nome que o arquivo traz.
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo "oi"; ?>'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'O arquivo enviado não é uma imagem JPG, PNG ou WEBP.');

        $this->assertSame([], Storage::disk(self::DISCO)->allFiles());
        $this->assertDatabaseCount('occurrence_attachments', 0);
    }

    public function test_recusa_extensao_que_nao_e_de_imagem(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->create('orcamento.pdf', 10, 'application/pdf'))
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'Envie uma imagem JPG, PNG ou WEBP.');
    }

    public function test_recusa_arquivo_acima_do_limite(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->create('enorme.jpg', 2048, 'image/jpeg'))
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'A imagem deve ter no máximo 1 MB.');
    }

    public function test_recusa_resolucao_acima_do_limite(): void
    {
        // Decodificar ocupa uns 4 bytes por pixel: o limite protege o
        // memory_limit do host, que derrubaria a requisição sem mensagem.
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->image('panoramica.jpg', 2500, 2000))
            ->assertStatus(422)
            ->assertJsonPath('message', 'A imagem tem 5.0 megapixels; o máximo é 4. Envie uma foto em resolução menor.');
    }

    public function test_exige_a_imagem(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        $this->actingAs($morador)
            ->postJson("/api/occurrences/{$chamado->id}/attachments", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'Selecione a imagem a anexar.');
    }

    public function test_chamado_encerrado_nao_recebe_imagem(): void
    {
        $morador = User::factory()->resident()->create();

        foreach ([OccurrenceStatus::Completed, OccurrenceStatus::Cancelled] as $final) {
            $chamado = $this->chamadoDe($morador, ['status' => $final]);

            $this->enviar($morador, $chamado, UploadedFile::fake()->image('tarde.jpg', 100, 100))
                ->assertStatus(409)
                ->assertJsonPath('message', "Não é possível anexar imagens a um chamado {$final->label()}.");
        }
    }

    public function test_chamado_em_qualquer_etapa_aberta_recebe_imagem(): void
    {
        $morador = User::factory()->resident()->create();

        foreach ([OccurrenceStatus::Open, OccurrenceStatus::Analysis, OccurrenceStatus::Assigned, OccurrenceStatus::InProgress] as $status) {
            $chamado = $this->chamadoDe($morador, ['status' => $status]);

            $this->enviar($morador, $chamado, UploadedFile::fake()->image('foto.jpg', 100, 100))
                ->assertStatus(201);
        }
    }

    public function test_limite_de_imagens_por_chamado(): void
    {
        // Cada imagem é um inode, e a conta gratuita tem por volta de 30 mil.
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);

        foreach (range(1, 3) as $i) {
            $this->enviar($morador, $chamado, UploadedFile::fake()->image("foto{$i}.jpg", 100, 100))->assertStatus(201);
        }

        $this->enviar($morador, $chamado, UploadedFile::fake()->image('quarta.jpg', 100, 100))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este chamado já tem 3 imagens, o máximo permitido.');
    }

    public function test_o_morador_nao_anexa_no_chamado_de_outro(): void
    {
        $chamado = $this->chamadoDe(User::factory()->resident()->create());

        $this->enviar(User::factory()->resident()->create(), $chamado, UploadedFile::fake()->image('foto.jpg', 100, 100))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem acesso a este chamado.');

        $this->assertSame([], Storage::disk(self::DISCO)->allFiles());
    }

    public function test_o_funcionario_anexa_no_chamado_que_atende(): void
    {
        // A foto do conserto feito.
        $funcionario = User::factory()->employee()->create();
        $chamado = Occurrence::factory()->inProgressWith($funcionario)->create();

        $this->enviar($funcionario, $chamado, UploadedFile::fake()->image('conserto.jpg', 100, 100))
            ->assertStatus(201);

        $this->enviar(User::factory()->employee()->create(), $chamado, UploadedFile::fake()->image('alheio.jpg', 100, 100))
            ->assertStatus(403);
    }

    public function test_sindico_e_administracao_anexam_em_qualquer_chamado(): void
    {
        $chamado = $this->chamadoDe(User::factory()->resident()->create());

        foreach ([User::factory()->syndic()->create(), User::factory()->admin()->create()] as $gestor) {
            $this->enviar($gestor, $chamado, UploadedFile::fake()->image('vistoria.jpg', 100, 100))
                ->assertStatus(201);
        }
    }

    public function test_registra_no_historico_e_na_auditoria(): void
    {
        $morador = User::factory()->resident()->create(['name' => 'Ana Silva']);
        $chamado = $this->chamadoDe($morador, ['title' => 'Vazamento no corredor']);

        $this->enviar($morador, $chamado, UploadedFile::fake()->image('vazamento.jpg', 100, 100));

        $anexo = OccurrenceAttachment::first();

        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $chamado->id,
            'user_id' => $morador->id,
            'type' => OccurrenceHistoryType::Comment->value,
            'message' => 'Imagem anexada: vazamento.jpg.',
        ]);

        $this->assertDatabaseHas('logs', [
            'type_log_id' => TypeLogEnum::OCCURRENCE->value,
            'description' => "Imagem {$anexo->id} anexada à ocorrência {$chamado->id} Vazamento no corredor por Ana Silva.",
        ]);
    }

    public function test_lista_as_imagens_do_chamado(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);
        $outro = $this->chamadoDe($morador);

        $this->enviar($morador, $chamado, UploadedFile::fake()->image('primeira.jpg', 100, 100));
        $this->enviar($morador, $chamado, UploadedFile::fake()->image('segunda.jpg', 100, 100));
        $this->enviar($morador, $outro, UploadedFile::fake()->image('de-outro-chamado.jpg', 100, 100));

        $this->actingAs($morador)
            ->getJson("/api/occurrences/{$chamado->id}/attachments")
            ->assertStatus(201)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.original_name', 'primeira.jpg')
            ->assertJsonPath('data.1.original_name', 'segunda.jpg')
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonStructure(['data' => [['id', 'url', 'mime_type', 'width', 'height', 'size', 'uploaded_by', 'created_at']]]);
    }

    public function test_listagem_vazia_devolve_lista_vazia(): void
    {
        $morador = User::factory()->resident()->create();

        $this->actingAs($morador)
            ->getJson("/api/occurrences/{$this->chamadoDe($morador)->id}/attachments")
            ->assertStatus(201)
            ->assertJsonPath('total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_quem_nao_ve_o_chamado_nao_lista_as_imagens(): void
    {
        $chamado = $this->chamadoDe(User::factory()->resident()->create());

        $this->actingAs(User::factory()->resident()->create())
            ->getJson("/api/occurrences/{$chamado->id}/attachments")
            ->assertStatus(403);
    }

    public function test_entrega_a_imagem_com_cabecalhos_de_protecao(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);
        $id = $this->enviar($morador, $chamado, UploadedFile::fake()->image('vazamento.jpg', 100, 100))->json('data.id');

        $resposta = $this->actingAs($morador)
            ->get("/api/occurrences/{$chamado->id}/attachments/{$id}")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString("default-src 'none'", $resposta->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('private', $resposta->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $resposta->headers->get('Content-Disposition'));
    }

    public function test_a_imagem_so_vale_dentro_do_proprio_chamado(): void
    {
        // Sem amarrar a imagem ao chamado da rota, quem enxerga um chamado
        // leria as imagens de qualquer outro trocando o número no endereço.
        $morador = User::factory()->resident()->create();
        $meu = $this->chamadoDe($morador);

        $alheio = $this->chamadoDe(User::factory()->resident()->create());
        $idAlheio = $this->enviar($alheio->resident, $alheio, UploadedFile::fake()->image('alheia.jpg', 100, 100))->json('data.id');

        $this->actingAs($morador)
            ->getJson("/api/occurrences/{$meu->id}/attachments/{$idAlheio}")
            ->assertStatus(404)
            ->assertJsonPath('message', "Imagem {$idAlheio} não encontrada nesta ocorrência.");
    }

    public function test_quem_nao_ve_o_chamado_nao_recebe_a_imagem(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);
        $id = $this->enviar($morador, $chamado, UploadedFile::fake()->image('vazamento.jpg', 100, 100))->json('data.id');

        $this->actingAs(User::factory()->resident()->create())
            ->getJson("/api/occurrences/{$chamado->id}/attachments/{$id}")
            ->assertStatus(403);
    }

    public function test_arquivo_sumido_do_disco_responde_404(): void
    {
        $morador = User::factory()->resident()->create();
        $chamado = $this->chamadoDe($morador);
        $id = $this->enviar($morador, $chamado, UploadedFile::fake()->image('vazamento.jpg', 100, 100))->json('data.id');

        Storage::disk(self::DISCO)->delete(OccurrenceAttachment::find($id)->path);

        $this->actingAs($morador)
            ->getJson("/api/occurrences/{$chamado->id}/attachments/{$id}")
            ->assertStatus(404)
            ->assertJsonPath('message', "O arquivo da imagem {$id} não está mais no servidor.");
    }

    public function test_chamado_inexistente_responde_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/occurrences/999/attachments')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Ocorrência 999 não encontrada.');
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/occurrences/1/attachments')->assertStatus(401);
        $this->postJson('/api/occurrences/1/attachments')->assertStatus(401);
        $this->getJson('/api/occurrences/1/attachments/1')->assertStatus(401);
    }
}
