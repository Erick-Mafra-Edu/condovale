<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RNF01 — o relatório de reservas responde abaixo de 2 segundos sob carga.
 *
 * A carga é a da UC-T01 (ListingPerformanceTest): dez mil reservas em cinco
 * áreas comuns, espalhadas por mil dias. Como lá, o SQLite daqui não prevê o
 * tempo do MySQL da hospedagem; o que o teste prende é consulta por linha e
 * exportação sem teto.
 */
class ReservationReportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const RESERVAS = 10000;

    private const DIAS = 1000;

    private const AREAS = 5;

    private const MORADORES = 600;

    private const UNIDADES = 300;

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

        $areas = [];

        for ($i = 1; $i <= self::AREAS; $i++) {
            $areas[] = [
                'name' => "Área comum {$i}",
                'capacity' => 30,
                'opening_time' => '08:00:00',
                'closing_time' => '22:00:00',
                'max_reservation_minutes' => 240,
                'requires_approval' => $i === 1,
                'status' => 'available',
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        DB::table('common_areas')->insert($areas);

        $moradores = [];

        for ($i = 1; $i <= self::MORADORES; $i++) {
            $moradores[] = [
                'name' => "Morador {$i}",
                'email' => "reserva{$i}@condovale.test",
                'password' => $hash,
                'role' => UserRole::Resident->value,
                'status' => UserStatus::Active->value,
                'unit_id' => (($i - 1) % self::UNIDADES) + 1,
                'must_change_password' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        foreach (array_chunk($moradores, 200) as $lote) {
            DB::table('users')->insert($lote);
        }

        $primeiroMorador = DB::table('users')->where('email', 'reserva1@condovale.test')->value('id');
        $primeiraArea = DB::table('common_areas')->min('id');
        $estados = ReservationStatus::cases();
        $inicio = now()->subDays(self::DIAS)->startOfDay();

        $reservas = [];

        for ($i = 0; $i < self::RESERVAS; $i++) {
            $diaria = $i % 7 === 0;

            $reservas[] = [
                'common_area_id' => $primeiraArea + ($i % self::AREAS),
                'resident_id' => $primeiroMorador + ($i % self::MORADORES),
                'date' => $inicio->copy()->addDays(intdiv($i * self::DIAS, self::RESERVAS))->toDateString(),
                'start_time' => $diaria ? null : sprintf('%02d:00:00', 8 + $i % 12),
                'end_time' => $diaria ? null : sprintf('%02d:00:00', 10 + $i % 12),
                'status' => $estados[$i % count($estados)]->value,
                'rejection_reason' => null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        foreach (array_chunk($reservas, 500) as $lote) {
            DB::table('reservations')->insert($lote);
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
        return '/api/reports/reservations?'.http_build_query($parametros + [
            'from' => now()->subDays($diasAtras)->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function test_o_relatorio_responde_dentro_do_orcamento(): void
    {
        $this->montarCarga();

        $sindico = User::factory()->syndic()->create();
        $area = DB::table('common_areas')->min('id');

        $casos = [
            'um ano, primeira página' => $this->url(365),
            'um ano, 100 por página' => $this->url(365, ['limit' => 100]),
            'um ano, última página' => $this->url(365, ['limit' => 10, 'page' => 366]),
            'um ano, filtrado por área e status' => $this->url(365, ['common_area_id' => $area, 'status' => 'approved']),
            'todo o histórico, primeira página' => $this->url(self::DIAS),
            'três meses, exportação completa' => $this->url(90, ['export' => 'true']),
        ];

        fwrite(STDERR, "\n  Carga: ".self::RESERVAS.' reservas em '.self::AREAS.' áreas, '.self::DIAS." dias\n");

        foreach ($casos as $caso => $url) {
            [$ms, , $resposta] = $this->medir(fn () => $this->actingAs($sindico)->getJson($url));

            fwrite(STDERR, sprintf("  %-40s %7.1f ms  %s linhas\n", $caso, $ms, $resposta->json('data.reservations.total')));

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
            fn () => $this->actingAs(User::factory()->syndic()->create())->getJson($this->url(self::DIAS, ['export' => 'true']))
        );

        fwrite(STDERR, sprintf("\n  %-40s %7.1f ms\n", 'histórico inteiro, exportação recusada', $ms));

        $resposta->assertStatus(422);
        $this->assertLessThan(self::ORCAMENTO_MS, $ms);
    }

    public function test_o_relatorio_nao_consulta_uma_vez_por_linha(): void
    {
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
