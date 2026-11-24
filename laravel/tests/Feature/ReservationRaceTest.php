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
     * Um morador por pedido de reserva, todos largando juntos.
     *
     * @param  list<array{0: ?string, 1: ?string}>  $periodos
     * @return Collection<int, string>
     */
    private function disputar(CommonArea $area, string $data, array $periodos): Collection
    {
        $moradores = User::factory()->resident()->count(count($periodos))->create();

        return $this->largar(array_map(
            fn ($i) => ['reservar', $area->id, $moradores[$i]->id, $data, $periodos[$i][0] ?? '-', $periodos[$i][1] ?? '-'],
            array_keys($periodos)
        ));
    }

    /**
     * Dispara um processo por comando do worker, todos largando no mesmo
     * instante, e devolve o que cada um respondeu, na ordem dos comandos.
     *
     * @param  list<list<string|int>>  $comandos
     * @return Collection<int, string>
     */
    private function largar(array $comandos): Collection
    {
        $largada = sprintf('%.6f', microtime(true) + self::LARGADA_SEGUNDOS);
        $processos = [];
        $saidas = [];

        foreach ($comandos as $comando) {
            $processos[] = proc_open(
                [PHP_BINARY, base_path('tests/Support/reservation-race-worker.php'), $largada, ...array_map('strval', $comando)],
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

    /**
     * RN08 — o morador cancela no mesmo instante em que a administração
     * aprova. Os dois processos carregam o pedido ainda pendente; sem reler o
     * status com trava, a aprovação gravaria "aprovada" por cima do
     * cancelamento e o período nunca seria liberado. Qualquer que seja a
     * ordem, o fim certo é cancelada: aprovada e depois cancelada, ou
     * cancelada e a aprovação recusada.
     */
    public function test_cancelar_e_aprovar_ao_mesmo_tempo_termina_cancelada(): void
    {
        $area = CommonArea::factory()->create([
            'opening_time' => '08:00', 'closing_time' => '22:00', 'max_reservation_minutes' => 240, 'requires_approval' => true,
        ]);
        $admin = User::factory()->admin()->create();
        // Seis pares, doze processos: mais chances de a corrida acontecer.
        $pedidos = collect(range(1, 6))->map(fn ($i) => Reservation::factory()->create([
            'common_area_id' => $area->id,
            'resident_id' => User::factory()->resident()->create()->id,
            'date' => now()->addDays(2 + $i)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]));

        $respostas = $this->largar($pedidos->flatMap(fn ($pedido) => [
            ['cancelar', $pedido->id, $pedido->resident_id],
            ['aprovar', $pedido->id, $admin->id],
        ])->all());

        $cancelamentos = $respostas->filter(fn ($r, $i) => $i % 2 === 0)->values();
        $aprovacoes = $respostas->filter(fn ($r, $i) => $i % 2 === 1)->values();

        fwrite(STDERR, "\n  cancelar x aprovar: aprovações ".$aprovacoes->countBy()->map(fn ($n, $r) => "{$r} x{$n}")->join(', ')."\n");

        $this->assertSame(array_fill(0, $pedidos->count(), 'ok'), $cancelamentos->all(), $respostas->join(' | '));
        $this->assertTrue($aprovacoes->every(fn ($r) => in_array($r, ['ok', 'refused:409'], true)), $respostas->join(' | '));
        $this->assertSame(
            array_fill(0, $pedidos->count(), 'cancelled'),
            $pedidos->map(fn ($pedido) => $pedido->refresh()->status->value)->all()
        );
    }
}
