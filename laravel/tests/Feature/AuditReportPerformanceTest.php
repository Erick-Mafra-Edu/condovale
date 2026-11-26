<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RNF01 — o relatório de auditoria responde abaixo de 2 segundos sob carga.
 *
 * `logs` recebe uma linha por operação e é a tabela que mais cresce: a carga
 * tem o dobro de linhas dos outros relatórios, vinte mil registros em mil
 * dias. Como nos demais, o SQLite daqui não prevê o tempo do MySQL da
 * hospedagem; o que o teste prende é consulta por linha e exportação sem teto.
 */
class AuditReportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTROS = 20000;

    private const DIAS = 1000;

    private const PESSOAS = 40;

    /** O teto da RNF01, em milissegundos. */
    private const ORCAMENTO_MS = 2000;

    private function montarCarga(): void
    {
        $agora = now()->toDateTimeString();
        $hash = User::factory()->resident()->create(['email' => 'semente@condovale.test'])->password;
        $perfis = UserRole::cases();

        $pessoas = [];

        for ($i = 1; $i <= self::PESSOAS; $i++) {
            $pessoas[] = [
                'name' => "Pessoa {$i}",
                'email' => "auditoria{$i}@condovale.test",
                'password' => $hash,
                'role' => $perfis[$i % count($perfis)]->value,
                'status' => UserStatus::Active->value,
                'must_change_password' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        DB::table('users')->insert($pessoas);

        $primeira = DB::table('users')->where('email', 'auditoria1@condovale.test')->value('id');
        $acoes = AuditAction::cases();
        $inicio = now()->subDays(self::DIAS)->startOfDay();
        $segundosPorRegistro = intdiv(self::DIAS * 86400, self::REGISTROS);

        $registros = [];

        for ($i = 0; $i < self::REGISTROS; $i++) {
            $acao = $acoes[$i % count($acoes)];
            $pessoa = $i % self::PESSOAS;
            $quando = $inicio->copy()->addSeconds($i * $segundosPorRegistro)->toDateTimeString();

            $registros[] = [
                'type_log_id' => $acao->typeLog()->value,
                'action' => $acao->value,
                'subject_id' => 1 + $i % 500,
                'user_id' => $primeira + $pessoa,
                'user_role' => $perfis[($pessoa + 1) % count($perfis)]->value,
                'description' => "{$acao->label()} ".(1 + $i % 500).'.',
                'ip' => '10.0.0.1',
                'data_log' => json_encode(['de' => ['status' => 'active'], 'para' => ['status' => 'inactive']]),
                'created_at' => $quando,
                'updated_at' => $quando,
            ];
        }

        foreach (array_chunk($registros, 500) as $lote) {
            DB::table('logs')->insert($lote);
        }
    }

    /**
     * Executa a chamada e devolve [milissegundos, consultas, resposta].
     */
    private function medir(callable $acao): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $inicio = hrtime(true);
        $resultado = $acao();
        $ms = (hrtime(true) - $inicio) / 1_000_000;

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$ms, $consultas, $resultado];
    }

    private function url(int $diasAtras, array $parametros = []): string
    {
        return '/api/audit-logs?'.http_build_query($parametros + [
            'from' => now()->subDays($diasAtras)->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function test_o_relatorio_responde_dentro_do_orcamento(): void
    {
        $this->montarCarga();

        $admin = User::factory()->admin()->create();
        $pessoa = DB::table('users')->where('email', 'auditoria3@condovale.test')->value('id');

        $casos = [
            'um ano, primeira página' => $this->url(365),
            'um ano, 100 por página' => $this->url(365, ['limit' => 100]),
            'um ano, última página' => $this->url(365, ['limit' => 10, 'page' => 731]),
            'um ano, por entidade' => $this->url(365, ['entity' => 'reservation']),
            'histórico de um registro' => $this->url(self::DIAS, ['entity' => 'reservation', 'entity_id' => 42]),
            'um ano, por responsável' => $this->url(365, ['user_id' => $pessoa]),
            'um ano, por perfil e ação' => $this->url(365, ['role' => 'employee', 'action' => 'occurrence.status_updated']),
            'todo o histórico, primeira página' => $this->url(self::DIAS),
            'um mês, exportação completa' => $this->url(30, ['export' => 'true']),
        ];

        fwrite(STDERR, "\n  Carga: ".self::REGISTROS.' registros de auditoria em '.self::DIAS." dias\n");

        foreach ($casos as $caso => $url) {
            [$ms, , $resposta] = $this->medir(fn () => $this->actingAs($admin)->getJson($url));

            fwrite(STDERR, sprintf("  %-40s %7.1f ms  %s linhas\n", $caso, $ms, $resposta->json('data.logs.total')));

            $resposta->assertStatus(201);

            $this->assertLessThan(
                self::ORCAMENTO_MS,
                $ms,
                "{$caso} levou {$ms} ms, acima do teto de ".self::ORCAMENTO_MS.' ms da RNF01.'
            );
        }
    }

    public function test_exportar_o_historico_inteiro_e_recusado_antes_de_carregar_as_linhas(): void
    {
        $this->montarCarga();

        [$ms, , $resposta] = $this->medir(
            fn () => $this->actingAs(User::factory()->admin()->create())->getJson($this->url(self::DIAS, ['export' => 'true']))
        );

        fwrite(STDERR, sprintf("\n  %-40s %7.1f ms\n", 'histórico inteiro, exportação recusada', $ms));

        $resposta->assertStatus(422);
        $this->assertLessThan(self::ORCAMENTO_MS, $ms);
    }

    public function test_o_relatorio_nao_consulta_uma_vez_por_linha(): void
    {
        $this->montarCarga();

        $admin = User::factory()->admin()->create();

        // Aquece o que é resolvido uma vez por processo (permissões do Spatie).
        $this->actingAs($admin)->getJson($this->url(365, ['limit' => 50]))->assertStatus(201);

        [, $comCinquenta] = $this->medir(fn () => $this->actingAs($admin)->getJson($this->url(365, ['limit' => 50]))->assertStatus(201));
        [, $comCem] = $this->medir(fn () => $this->actingAs($admin)->getJson($this->url(365, ['limit' => 100]))->assertStatus(201));
        [, $exportando] = $this->medir(fn () => $this->actingAs($admin)->getJson($this->url(30, ['export' => 'true']))->assertStatus(201));

        fwrite(STDERR, "\n  {$comCinquenta} consultas com 50 linhas, {$comCem} com 100, {$exportando} na exportação de um mês\n");

        $this->assertSame($comCinquenta, $comCem, 'O relatório passou a consultar uma vez por linha.');
        $this->assertSame($comCinquenta, $exportando, 'A exportação passou a consultar uma vez por linha.');
    }
}
