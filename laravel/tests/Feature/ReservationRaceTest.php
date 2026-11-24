<?php

namespace Tests\Feature;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * RN01 / RSV-02 — pedidos simultâneos para o mesmo período gravam uma reserva só.
 *
 * Só roda contra MySQL: a disputa é entre processos PHP de verdade, cada um com
 * a sua conexão e a sua transação, e o SQLite em memória da suíte não é
 * compartilhado entre processos — além de ignorar o `lockForUpdate`. Na suíte
 * comum este teste é pulado. Para rodar, aponte as variáveis DB_* para um
 * banco MySQL vazio (ver "Conflito de reservas" no README).
 *
 * Usa DatabaseTruncation, e não RefreshDatabase: os dados precisam estar
 * confirmados no banco para os outros processos enxergarem. Também não usa
 * DatabaseMigrations, que desfaz as migrations a cada teste — e o down() de
 * occurrence_types não roda no MySQL.
 */
class ReservationRaceTest extends TestCase
{
    use DatabaseTruncation;

    private const PEDIDOS = 8;

    /** Tempo para todos os processos subirem a aplicação antes da largada. */
    private const LARGADA_SEGUNDOS = 4;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('A disputa entre processos só é possível contra MySQL.');
        }

        parent::setUp();

        // O que a disputa grava fica confirmado e apareceria para os testes
        // seguintes, que rodam dentro de transação sobre o mesmo banco: limpa
        // e devolve a carga de referência (papéis e permissões).
        $this->beforeApplicationDestroyed(function () {
            $this->truncateTablesForAllConnections();
            $this->artisan('db:seed', ['--class' => $this->seeder()]);
        });
    }

    private function area(): CommonArea
    {
        return CommonArea::factory()->create([
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'max_reservation_minutes' => 240,
            'requires_approval' => false,
        ]);
    }

    /**
     * Dispara um processo por pedido, todos largando no mesmo instante, e
     * devolve o que cada um respondeu.
     *
     * @param  list<array{0: ?string, 1: ?string}>  $periodos
     * @return Collection<int, string>
     */
    private function disputar(CommonArea $area, string $data, array $periodos): Collection
    {
        $moradores = User::factory()->resident()->count(count($periodos))->create();
        $largada = sprintf('%.6f', microtime(true) + self::LARGADA_SEGUNDOS);
        $processos = [];
        $saidas = [];

        foreach ($periodos as $i => [$inicio, $fim]) {
            $processos[] = proc_open(
                [PHP_BINARY, base_path('tests/Support/reservation-race-worker.php'), (string) $area->id, (string) $moradores[$i]->id, $data, $inicio ?? '-', $fim ?? '-', $largada],
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

        return collect($respostas);
    }

    public function test_pedidos_simultaneos_para_o_mesmo_periodo_gravam_uma_reserva_so(): void
    {
        $area = $this->area();
        $data = now()->addDays(3)->toDateString();

        $respostas = $this->disputar($area, $data, array_fill(0, self::PEDIDOS, ['10:00', '12:00']));

        fwrite(STDERR, "\n  mesmo período: ".$respostas->countBy()->map(fn ($n, $r) => "{$r} x{$n}")->join(', ')."\n");

        $this->assertSame(1, $respostas->filter(fn ($r) => $r === 'created')->count(), $respostas->join(' | '));
        $this->assertSame(self::PEDIDOS - 1, $respostas->filter(fn ($r) => $r === 'refused:409')->count(), $respostas->join(' | '));
        $this->assertSame(1, Reservation::where('common_area_id', $area->id)->count());
    }

    public function test_diaria_e_horarios_sobrepostos_simultaneos_gravam_uma_reserva_so(): void
    {
        $area = $this->area();
        $data = now()->addDays(3)->toDateString();

        // Metade pede a diária; a outra metade, períodos que passam todos
        // pelo bloco das 10h às 10h30, então qualquer par se cruza.
        $periodos = [[null, null], ['09:00', '11:00'], [null, null], ['10:00', '12:00'], [null, null], ['09:30', '10:30'], [null, null], ['10:00', '11:00']];

        $respostas = $this->disputar($area, $data, $periodos);

        fwrite(STDERR, "\n  diária e horários: ".$respostas->countBy()->map(fn ($n, $r) => "{$r} x{$n}")->join(', ')."\n");

        $this->assertSame(1, $respostas->filter(fn ($r) => $r === 'created')->count(), $respostas->join(' | '));
        $this->assertSame(1, Reservation::where('common_area_id', $area->id)->count());
    }

    public function test_periodos_que_nao_se_cruzam_passam_todos_mesmo_juntos(): void
    {
        $area = $this->area();
        $data = now()->addDays(3)->toDateString();

        // A trava enfileira os pedidos, mas não pode recusar quem não conflita.
        $periodos = [['08:00', '10:00'], ['10:00', '12:00'], ['12:00', '14:00'], ['14:00', '16:00']];

        $respostas = $this->disputar($area, $data, $periodos);

        $this->assertSame(array_fill(0, 4, 'created'), $respostas->all());
        $this->assertSame(4, Reservation::where('common_area_id', $area->id)->count());
    }
}
