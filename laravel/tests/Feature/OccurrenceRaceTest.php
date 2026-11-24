<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Models\Log;
use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * OCR-10 — duas requisições que mudam o mesmo chamado ao mesmo tempo efetivam
 * uma transição só, sem histórico nem auditoria duplicados.
 *
 * Como o ReservationRaceTest, só roda contra MySQL: a disputa é entre
 * processos PHP de verdade, e o SQLite em memória da suíte não é compartilhado
 * entre processos nem respeita o `lockForUpdate`. Na suíte comum é pulado.
 */
class OccurrenceRaceTest extends TestCase
{
    use DatabaseTruncation;

    private const PEDIDOS = 6;

    /** Tempo para todos os processos subirem a aplicação antes da largada. */
    private const LARGADA_SEGUNDOS = 4;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('A disputa entre processos só é possível contra MySQL.');
        }

        parent::setUp();

        $this->beforeApplicationDestroyed(function () {
            $this->truncateTablesForAllConnections();
            $this->artisan('db:seed', ['--class' => $this->seeder()]);
        });
    }

    /**
     * Um processo por pedido, todos largando no mesmo instante; devolve o que
     * cada um respondeu.
     *
     * @return Collection<int, string>
     */
    private function largar(string $acao, Occurrence $chamado, User $quem): Collection
    {
        $largada = sprintf('%.6f', microtime(true) + self::LARGADA_SEGUNDOS);
        $processos = [];
        $saidas = [];

        for ($i = 0; $i < self::PEDIDOS; $i++) {
            $processos[] = proc_open(
                [PHP_BINARY, base_path('tests/Support/occurrence-race-worker.php'), $largada, $acao, (string) $chamado->id, (string) $quem->id],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            $saidas[] = $pipes;
        }

        $respostas = [];

        foreach ($processos as $i => $processo) {
            $respostas[] = trim(stream_get_contents($saidas[$i][1]).stream_get_contents($saidas[$i][2]));
            fclose($saidas[$i][1]);
            fclose($saidas[$i][2]);
            proc_close($processo);
        }

        fwrite(STDERR, "\n  {$acao}: ".collect($respostas)->countBy()->map(fn ($n, $r) => "{$r} x{$n}")->join(', ')."\n");

        return collect($respostas);
    }

    public function test_finalizar_o_mesmo_chamado_varias_vezes_ao_mesmo_tempo_encerra_uma_vez(): void
    {
        $admin = User::factory()->admin()->create();
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::InProgress]);

        $respostas = $this->largar('finalizar', $chamado, $admin);

        $this->assertSame(1, $respostas->filter(fn ($r) => $r === 'ok')->count(), $respostas->join(' | '));
        $this->assertSame(self::PEDIDOS - 1, $respostas->filter(fn ($r) => str_starts_with($r, 'refused:'))->count(), $respostas->join(' | '));
        $this->assertSame(OccurrenceStatus::Completed, $chamado->refresh()->status);
        $this->assertSame(1, OccurrenceHistory::where('occurrence_id', $chamado->id)->where('type', OccurrenceHistoryType::Completed)->count());
        $this->assertSame(1, Log::where('action', AuditAction::OccurrenceCompleted->value)->where('subject_id', $chamado->id)->count());
    }

    public function test_analisar_o_mesmo_chamado_ao_mesmo_tempo_registra_uma_transicao(): void
    {
        $sindico = User::factory()->syndic()->create();
        $chamado = Occurrence::factory()->create(['status' => OccurrenceStatus::Open]);

        $respostas = $this->largar('analisar', $chamado, $sindico);

        $this->assertSame(1, $respostas->filter(fn ($r) => $r === 'ok')->count(), $respostas->join(' | '));
        $this->assertSame(OccurrenceStatus::Analysis, $chamado->refresh()->status);
        $this->assertSame(1, OccurrenceHistory::where('occurrence_id', $chamado->id)->where('type', OccurrenceHistoryType::StatusChanged)->count());
        $this->assertSame(1, Log::where('action', AuditAction::OccurrenceStatusUpdated->value)->where('subject_id', $chamado->id)->count());
    }
}
