<?php

namespace Tests\Feature;

use App\Models\Unit;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeployRouteTest extends TestCase
{
    private const VALID_TOKEN = 'a0b1c2d3e4f50a1b2c3d4e5f60718293';

    public function test_it_answers_404_while_the_deployment_is_disabled(): void
    {
        config(['deploy.enabled' => false, 'deploy.token' => self::VALID_TOKEN]);

        $this->postJson('/api/deploy/migrate', ['token' => self::VALID_TOKEN])
            ->assertStatus(404)
            ->assertJson(['status' => false]);
    }

    public function test_it_refuses_to_run_with_a_token_short_enough_to_guess(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => 'curto']);

        $this->postJson('/api/deploy/migrate', ['token' => 'curto'])
            ->assertStatus(404);
    }

    public function test_it_rejects_a_wrong_token(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->postJson('/api/deploy/migrate', ['token' => str_repeat('b', 32)])
            ->assertStatus(403)
            ->assertJson(['status' => false]);
    }

    public function test_it_recreates_the_schema_with_the_right_token(): void
    {
        Storage::fake('occurrence_attachments');
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/migrate')
            ->assertStatus(201)
            ->assertJson(['status' => true]);

        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_aplica_so_as_pendentes_sem_apagar_dados(): void
    {
        // Recriar apaga o que o time cadastrou testando a interface desde a
        // última carga. Quando a mudança é só uma tabela nova, o resto fica.
        Storage::fake('occurrence_attachments');
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/migrate')
            ->assertStatus(201);

        // O que existe depois da carga é o que a recarga parcial não pode levar.
        Unit::create(['code' => 'Z-999', 'number' => '999']);
        Storage::disk('occurrence_attachments')->put('1/a.jpg', 'x');

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/migrate', ['fresh' => false])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Migrations pendentes aplicadas.');

        $this->assertDatabaseHas('units', ['code' => 'Z-999']);
        Storage::disk('occurrence_attachments')->assertExists('1/a.jpg');
    }

    public function test_recriar_o_banco_apaga_os_anexos_que_ficariam_orfaos(): void
    {
        // As imagens pertencem às linhas que o migrate:fresh apaga. Deixá-las
        // no disco seria ocupar inodes de uma conta que tem por volta de 30
        // mil com arquivos que nenhum registro aponta.
        Storage::fake('occurrence_attachments');
        Storage::disk('occurrence_attachments')->put('1/a.jpg', 'x');
        Storage::disk('occurrence_attachments')->put('2/b.jpg', 'x');

        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/migrate')
            ->assertStatus(201);

        $this->assertSame([], Storage::disk('occurrence_attachments')->allFiles());
    }

    public function test_o_diagnostico_de_upload_diz_o_que_o_servidor_tem(): void
    {
        Storage::fake('occurrence_attachments');
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->getJson('/api/deploy/uploads')
            ->assertStatus(201)
            ->assertJsonStructure(['data' => [
                'php' => ['upload_max_filesize', 'post_max_size', 'memory_limit'],
                'limite_efetivo_kb',
                'extensoes' => ['gd', 'exif', 'fileinfo'],
                'regrava' => ['jpeg', 'png', 'webp'],
                'disco' => ['gravavel', 'livre_mb', 'anexos_gravados'],
            ]]);
    }

    public function test_o_diagnostico_de_upload_exige_o_token(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->getJson('/api/deploy/uploads')->assertStatus(403);
    }

    public function test_storage_nao_e_servido_pela_web(): void
    {
        // A raiz manda tudo para public/, mas só enquanto o mod_rewrite estiver
        // ativo. Este arquivo é a segunda tranca, e o publicador precisa
        // enviá-lo — de storage/ ele só mandava o .gitignore.
        $htaccess = base_path('storage/.htaccess');

        $this->assertFileExists($htaccess);
        $this->assertStringContainsString('Require all denied', file_get_contents($htaccess));

        $publicador = file_get_contents(base_path('../tools/deploy-infinityfree.py'));
        $this->assertStringContainsString('return name in (".gitignore", ".htaccess")', $publicador);
    }

    public function test_nao_ha_rota_servindo_o_disco_privado_por_url_assinada(): void
    {
        // Com `serve` ligado, o Laravel registra /storage/{path} e entrega
        // arquivo privado a quem tiver a URL assinada. Arquivo privado sai
        // pela rota que confere quem pode ver.
        $this->assertFalse(Route::has('storage.local'));
    }
}
