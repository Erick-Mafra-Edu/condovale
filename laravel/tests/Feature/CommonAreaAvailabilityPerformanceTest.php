<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Http\Requests\CommonAreaOccupancyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RNF01 — a consulta de disponibilidade responde abaixo de 2 segundos.
 *
 * Mesma escala dos relatórios: dez mil reservas em mil dias, espalhadas por
 * cinco áreas. A consulta pede a janela máxima aceita (62 dias). Além do
 * tempo, prende que o número de consultas não depende do volume: com o dobro
 * de reservas, a mesma quantidade de consultas.
 */
class CommonAreaAvailabilityPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const LINHAS = 10000;

    private const DIAS = 1000;

    private const AREAS = 5;

    /** O teto da RNF01, em milissegundos. */
    private const ORCAMENTO_MS = 2000;

    private function montarCarga(int $residentId, bool $criarAreas): void
    {
        $agora = now()->toDateTimeString();

        if ($criarAreas) {
            DB::table('common_areas')->insert(array_map(fn ($i) => [
                'name' => "Área {$i}",
                'capacity' => 30,
                'opening_time' => '08:00:00',
                'closing_time' => '22:00:00',
                'max_reservation_minutes' => 240,
                'requires_approval' => false,
                'status' => 'available',
                'created_at' => $agora,
                'updated_at' => $agora,
            ], range(1, self::AREAS)));
        }

        $estados = ReservationStatus::cases();

        // Gerado e gravado lote a lote, sem as dez mil linhas na memória de uma
        // vez: a suíte inteira roda num processo só.
        foreach (array_chunk(range(0, self::LINHAS - 1), 500) as $indices) {
            DB::table('reservations')->insert(array_map(function (int $i) use ($residentId, $estados, $agora) {
                // Uma diária a cada cinquenta; o resto em blocos de uma hora ao longo do dia.
                $diaria = $i % 50 === 0;
                $inicio = 8 + $i % 14;

                return [
                    'common_area_id' => 1 + $i % self::AREAS,
                    'resident_id' => $residentId,
                    // Metade no passado, metade no futuro: a janela consultada tem reservas.
                    'date' => now()->subDays(self::DIAS / 2)->addDays(intdiv($i * self::DIAS, self::LINHAS))->toDateString(),
                    'start_time' => $diaria ? null : sprintf('%02d:00:00', $inicio),
                    'end_time' => $diaria ? null : sprintf('%02d:00:00', $inicio + 1),
                    'status' => $estados[$i % count($estados)]->value,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }, $indices));
        }
    }

    /**
     * Executa a consulta e devolve [milissegundos, consultas, dias ocupados ou parciais].
     */
    private function medir(User $user, string $inicio, string $fim): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $comeco = hrtime(true);
        $resposta = $this->actingAs($user)
            ->getJson("/api/common-areas/1/availability?start_date={$inicio}&end_date={$fim}")
            ->assertStatus(201);
        $ms = (hrtime(true) - $comeco) / 1_000_000;

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $tomados = count(array_filter(
            $resposta->json('data.days'),
            fn ($dia) => in_array($dia['status'], ['partial', 'occupied'], true)
        ));

        return [$ms, $consultas, $tomados];
    }

    public function test_a_disponibilidade_responde_dentro_do_orcamento_e_sem_consulta_por_linha(): void
    {
        $morador = User::factory()->resident()->create();
        $inicio = now()->toDateString();
        $fim = now()->addDays(CommonAreaOccupancyRequest::MAX_DAYS - 1)->toDateString();

        $this->montarCarga($morador->id, true);

        // Aquece o que é resolvido uma vez por processo (permissões do Spatie).
        $this->medir($morador, $inicio, $fim);

        [$ms, $consultas, $tomados] = $this->medir($morador, $inicio, $fim);

        $this->montarCarga($morador->id, false);
        $this->medir($morador, $inicio, $fim);

        [$msDobro, $consultasDobro] = $this->medir($morador, $inicio, $fim);

        fwrite(STDERR, sprintf(
            "\n  Disponibilidade de %d dias com %d reservas na base\n  %7.1f ms, %d consultas, %d dias com reserva\n  %7.1f ms, %d consultas com o dobro de reservas\n",
            CommonAreaOccupancyRequest::MAX_DAYS, self::LINHAS, $ms, $consultas, $tomados, $msDobro, $consultasDobro
        ));

        // A carga precisa ter caído na janela, senão a medição não mede nada.
        // Pelo ciclo de status, a área 1 tem reserva que bloqueia em dia sim,
        // dia não: metade da janela.
        $this->assertGreaterThanOrEqual(intdiv(CommonAreaOccupancyRequest::MAX_DAYS, 2), $tomados);

        $this->assertLessThan(self::ORCAMENTO_MS, $ms, "A disponibilidade levou {$ms} ms.");
        $this->assertLessThan(self::ORCAMENTO_MS, $msDobro, "Com o dobro de reservas, a disponibilidade levou {$msDobro} ms.");
        $this->assertSame($consultas, $consultasDobro, 'A disponibilidade passou a consultar uma vez por linha.');
    }
}
