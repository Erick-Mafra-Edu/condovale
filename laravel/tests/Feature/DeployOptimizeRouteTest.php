<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * UC-T01 / RNF01 — a rota que gera os caches de inicialização no servidor.
 *
 * É o `php artisan optimize` que não há como rodar por SSH no plano gratuito.
 * Fica atrás do mesmo token das demais rotas de implantação: quem a chama pode
 * substituir a configuração em vigor da aplicação inteira.
 */
class DeployOptimizeRouteTest extends TestCase
{
    private const VALID_TOKEN = 'a0b1c2d3e4f50a1b2c3d4e5f60718293';

    protected function tearDown(): void
    {
        // Os caches são arquivos reais em bootstrap/cache e sobrevivem ao
        // teste: deixá-los faria a execução seguinte da suíte rodar com a
        // configuração congelada daqui, inclusive o banco.
        Artisan::call('config:clear');
        Artisan::call('route:clear');

        parent::tearDown();
    }

    public function test_responde_404_enquanto_a_implantacao_esta_desligada(): void
    {
        config(['deploy.enabled' => false, 'deploy.token' => self::VALID_TOKEN]);

        $this->postJson('/api/deploy/optimize', ['token' => self::VALID_TOKEN])
            ->assertStatus(404);
    }

    public function test_recusa_token_errado(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->postJson('/api/deploy/optimize', ['token' => str_repeat('b', 32)])
            ->assertStatus(403);
    }

    public function test_gera_os_caches_de_configuracao_e_de_rotas(): void
    {
        config(['deploy.enabled' => true, 'deploy.token' => self::VALID_TOKEN]);

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/optimize')
            ->assertStatus(201)
            ->assertJson(['status' => true]);

        $this->assertFileExists(base_path('bootstrap/cache/config.php'));
        $this->assertFileExists(base_path('bootstrap/cache/routes-v7.php'));
    }

    public function test_apaga_os_caches_quando_pedido(): void
    {
        // A partir daqui a configuração vem do arquivo gerado, e não mais do
        // `config()` em memória: um `config([...])` aplicado antes de cachear
        // não sobrevive ao cache. É por isso que o token precisa estar no
        // ambiente, e não só sobrescrito — é também o que acontece em
        // produção, onde o cache congela o que estava no `.env` no momento em
        // que foi gerado.
        // A partir do primeiro cache a configuracao vem do arquivo gerado, e
        // nao mais do `config()` em memoria: um override feito antes de
        // cachear nao sobrevive. O token do teste vem do phpunit.xml por isso
        // — e e tambem o que acontece em producao, onde o cache congela o que
        // estava no `.env` quando foi gerado.
        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/optimize')
            ->assertStatus(201);

        $this->assertFileExists(base_path('bootstrap/cache/config.php'));

        $this->withHeader('X-Deploy-Token', self::VALID_TOKEN)
            ->postJson('/api/deploy/optimize', ['clear' => true])
            ->assertStatus(201);

        $this->assertFileDoesNotExist(base_path('bootstrap/cache/config.php'));
        $this->assertFileDoesNotExist(base_path('bootstrap/cache/routes-v7.php'));
    }

    public function test_nenhuma_rota_e_closure(): void
    {
        // `route:cache` não serializa closure: uma rota declarada como função
        // anônima faz o comando falhar inteiro, e o servidor fica sem o cache
        // sem que ninguém perceba — a publicação segue normalmente, só mais
        // lenta. O comando é a própria verificação.
        $saida = Artisan::call('route:cache');

        $this->assertSame(0, $saida, 'route:cache falhou: há rota declarada como closure.');

        $this->assertFileExists(base_path('bootstrap/cache/routes-v7.php'));
    }

    public function test_o_publicador_apaga_os_caches_de_inicializacao(): void
    {
        // Cache de configuração velho é a pior falha possível: a aplicação
        // roda com o .env anterior e não acusa nada. Quem garante que isso não
        // sobreviva a um deploy é o publicador, e esta é a trava disso.
        $publicador = File::get(base_path('../tools/deploy-infinityfree.py'));

        foreach (['config.php', 'routes-v7.php', 'packages.php', 'services.php'] as $arquivo) {
            $this->assertStringContainsString(
                $arquivo,
                $publicador,
                "O publicador não apaga bootstrap/cache/{$arquivo} ao enviar arquivos."
            );
        }
    }
}
