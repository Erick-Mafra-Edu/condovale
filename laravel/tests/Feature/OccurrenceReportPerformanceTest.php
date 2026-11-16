<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RNF01 — o relatório de ocorrências responde abaixo de 2 segundos sob carga.
 *
 * A carga segue a escala da UC-T01 (ListingPerformanceTest): um condomínio de
 * porte médio com quase três anos de chamados, dez por dia. Como lá, o
 * SQLite daqui não prevê o tempo do MySQL da hospedagem; o que o teste prende
 * é o que nenhum servidor conserta — consulta por linha e exportação sem
 * teto.
 */
class OccurrenceReportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const CHAMADOS = 10000;

    private const DIAS = 1000;

    private const MORADORES = 600;

    private const UNIDADES = 300;

    private const FUNCIONARIOS = 10;

    private const TIPOS = 8;

    /** O teto da RNF01, em milissegundos. */
    private const ORCAMENTO_MS = 2000;

    private function montarCarga(): void
    {
        $agora = now()->toDateTimeString();
        $hash = User::factory()->resident()->create(['email' => 'semente@condovale.test'])->password;

        $unidades = [];

        for ($i = 1; $i <= self::UNIDADES; $i++) {
            $unidades[] = ['block' => 'Bloco A', 'number' => (string) $i, 'code' => 'A-'.$i, 'status' => 'active', 'created_at' => $agora, 'updated_at' => $agora];
        }

        DB::table('units')->insert($unidades);

        $tipos = [];

        for ($i = 1; $i <= self::TIPOS; $i++) {
            $tipos[] = ['name' => "Tipo {$i}", 'description' => null, 'active' => true, 'employee_can_close' => $i % 2 === 0, 'created_at' => $agora, 'updated_at' => $agora];
        }

        DB::table('occurrence_types')->insert($tipos);

        $usuarios = [];

        for ($i = 1; $i <= self::MORADORES + self::FUNCIONARIOS; $i++) {
            $funcionario = $i > self::MORADORES;
            $usuarios[] = [
                'name' => ($funcionario ? 'Funcionário ' : 'Morador ').$i,
                'email' => "relatorio{$i}@condovale.test",
                'password' => $hash,
                'role' => $funcionario ? UserRole::Employee->value : UserRole::Resident->value,
                'status' => UserStatus::Active->value,
                'unit_id' => $funcionario ? null : (($i - 1) % self::UNIDADES) + 1,
                'must_change_password' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        foreach (array_chunk($usuarios, 200) as $lote) {
            DB::table('users')->insert($lote);
        }

        $primeiroMorador = DB::table('users')->where('email', 'relatorio1@condovale.test')->value('id');
        $primeiroFuncionario = $primeiroMorador + self::MORADORES;
        $primeiroTipo = DB::table('occurrence_types')->min('id');
        $estados = OccurrenceStatus::cases();
        $inicio = now()->subDays(self::DIAS)->startOfDay();

        $chamados = [];

        for ($i = 0; $i < self::CHAMADOS; $i++) {
            $status = $estados[$i % count($estados)];
            $abertura = $inicio->copy()->addMinutes(intdiv($i * self::DIAS * 1440, self::CHAMADOS));
            $concluido = $status === OccurrenceStatus::Completed;

            $chamados[] = [
                'title' => "Chamado {$i}",
                'description' => 'Descrição do chamado para o relatório.',
                'occurrence_type_id' => $primeiroTipo + ($i % self::TIPOS),
                'resident_id' => $primeiroMorador + ($i % self::MORADORES),
                'unit_id' => ($i % self::UNIDADES) + 1,
                'assigned_employee_id' => in_array($status, [OccurrenceStatus::Open, OccurrenceStatus::Analysis], true) ? null : $primeiroFuncionario + ($i % self::FUNCIONARIOS),
                'status' => $status->value,
                'resolved' => $concluido ? $i % 5 !== 0 : null,
                'conclusion' => $concluido ? 'Atendimento concluído.' : null,
                'completed_at' => $concluido ? $abertura->copy()->addHours(1 + $i % 72)->toDateTimeString() : null,
                'created_at' => $abertura->toDateTimeString(),
                'updated_at' => $abertura->toDateTimeString(),
            ];
        }

        foreach (array_chunk($chamados, 500) as $lote) {
            DB::table('occurrences')->insert($lote);
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
        return '/api/reports/occurrences?'.http_build_query($parametros + [
            'from' => now()->subDays($diasAtras)->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function test_o_relatorio_responde_dentro_do_orcamento(): void
    {
        $this->montarCarga();

        $sindico = User::factory()->syndic()->create();
        $tipo = DB::table('occurrence_types')->min('id');

        $casos = [
            'um ano, primeira página' => $this->url(365),
            'um ano, 100 por página' => $this->url(365, ['limit' => 100]),
            'um ano, última página' => $this->url(365, ['limit' => 10, 'page' => 366]),
            'um ano, filtrado por tipo e status' => $this->url(365, ['occurrence_type_id' => $tipo, 'status' => 'completed']),
            'todo o histórico, primeira página' => $this->url(self::DIAS),
            'três meses, exportação completa' => $this->url(90, ['export' => 'true']),
        ];

        fwrite(STDERR, "\n  Carga: ".self::CHAMADOS.' chamados em '.self::DIAS." dias\n");

        foreach ($casos as $caso => $url) {
            [$ms, , $resposta] = $this->medir(fn () => $this->actingAs($sindico)->getJson($url));

            fwrite(STDERR, sprintf("  %-40s %7.1f ms  %s linhas\n", $caso, $ms, $resposta->json('data.occurrences.total')));

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
        // Dez mil chamados passam do teto de exportação: a recusa vem da
        // contagem, sem montar as linhas na memória.
        $this->montarCarga();

        [$ms, , $resposta] = $this->medir(
            fn () => $this->actingAs(User::factory()->syndic()->create())->getJson($this->url(self::DIAS, ['export' => 'true']))
        );

        fwrite(STDERR, sprintf("\n  %-40s %7.1f ms\n", 'histórico inteiro, exportação recusada', $ms));

        $resposta->assertStatus(422);
        $this->assertLessThan(self::ORCAMENTO_MS, $ms);
    }

    public function test_o_relatorio_nao_consulta_uma_vez_por_linha(): void
    {
        // Mesmo critério do ListingPerformanceTest: com 50 e com 100 linhas,
        // o número de consultas tem de ser o mesmo.
        $this->montarCarga();

        $sindico = User::factory()->syndic()->create();

        // Aquece o que é resolvido uma vez por processo (permissões do Spatie).
        $this->actingAs($sindico)->getJson($this->url(365, ['limit' => 50]))->assertStatus(201);

        [, $comCinquenta] = $this->medir(fn () => $this->actingAs($sindico)->getJson($this->url(365, ['limit' => 50]))->assertStatus(201));
        [, $comCem] = $this->medir(fn () => $this->actingAs($sindico)->getJson($this->url(365, ['limit' => 100]))->assertStatus(201));
        [, $exportando] = $this->medir(fn () => $this->actingAs($sindico)->getJson($this->url(90, ['export' => 'true']))->assertStatus(201));

        fwrite(STDERR, "\n  {$comCinquenta} consultas com 50 linhas, {$comCem} com 100, {$exportando} na exportação de três meses\n");

        $this->assertSame($comCinquenta, $comCem, 'O relatório passou a consultar uma vez por linha.');
        $this->assertSame($comCinquenta, $exportando, 'A exportação passou a consultar uma vez por linha.');
    }
}
