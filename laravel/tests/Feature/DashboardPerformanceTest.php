<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Enums\ReservationStatus;
use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RNF01 — o painel abre abaixo de 2 segundos, para qualquer perfil.
 *
 * O painel do administrador é o mais pesado: tem todas as seções. A carga
 * segue a escala dos testes dos relatórios — dez mil chamados e dez mil
 * reservas em mil dias. O que se prende aqui, além do tempo, é que o número
 * de consultas não depende do volume: o painel com a base vazia e com a
 * carga inteira dispara as mesmas consultas.
 */
class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const LINHAS = 10000;

    private const DIAS = 1000;

    private const MORADORES = 600;

    private const UNIDADES = 300;

    /** O teto da RNF01, em milissegundos. */
    private const ORCAMENTO_MS = 2000;

    /**
     * A primeira rodada cria unidades, tipos e áreas; as seguintes só
     * acrescentam pessoas, chamados, reservas e registros de auditoria, para
     * o volume crescer sem colidir nas colunas únicas.
     */
    private function montarCarga(int $rodada): void
    {
        $agora = now()->toDateTimeString();
        $hash = User::factory()->resident()->create(['email' => "semente{$rodada}@condovale.test"])->password;

        if ($rodada === 1) {
            DB::table('units')->insert(array_map(fn ($i) => [
                'block' => 'Bloco A', 'number' => (string) $i, 'code' => 'A-'.$i, 'status' => 'active', 'created_at' => $agora, 'updated_at' => $agora,
            ], range(1, self::UNIDADES)));

            DB::table('occurrence_types')->insert(array_map(fn ($i) => [
                'name' => "Tipo {$i}", 'active' => true, 'employee_can_close' => false, 'created_at' => $agora, 'updated_at' => $agora,
            ], range(1, 8)));

            DB::table('common_areas')->insert(array_map(fn ($i) => [
                'name' => "Área {$i}", 'capacity' => 30, 'requires_approval' => true, 'status' => 'available', 'created_at' => $agora, 'updated_at' => $agora,
            ], range(1, 5)));
        }

        foreach (array_chunk(range(1, self::MORADORES + 10), 200) as $lote) {
            DB::table('users')->insert(array_map(fn ($i) => [
                'name' => "Pessoa {$i}",
                'email' => "painel{$rodada}-{$i}@condovale.test",
                'password' => $hash,
                'role' => $i > self::MORADORES ? UserRole::Employee->value : UserRole::Resident->value,
                'status' => UserStatus::Active->value,
                'unit_id' => $i > self::MORADORES ? null : (($i - 1) % self::UNIDADES) + 1,
                'must_change_password' => $i % 50 === 0,
                'created_at' => $agora,
                'updated_at' => $agora,
            ], $lote));
        }

        $primeiro = DB::table('users')->where('email', "painel{$rodada}-1@condovale.test")->value('id');

        if ($rodada === 1) {
            foreach (array_chunk(range(1, self::UNIDADES), 200) as $lote) {
                DB::table('unit_occupancies')->insert(array_map(fn ($i) => [
                    'unit_id' => $i, 'user_id' => $primeiro + $i - 1, 'occupant_type' => 'owner', 'started_at' => '2024-01-01', 'is_active' => true, 'created_at' => $agora, 'updated_at' => $agora,
                ], $lote));
            }
        }

        $inicio = now()->subDays(self::DIAS)->startOfDay();
        $chamados = [];
        $reservas = [];
        $estadosChamado = OccurrenceStatus::cases();
        $estadosReserva = ReservationStatus::cases();

        for ($i = 0; $i < self::LINHAS; $i++) {
            $abertura = $inicio->copy()->addMinutes(intdiv($i * self::DIAS * 1440, self::LINHAS));
            $status = $estadosChamado[$i % count($estadosChamado)];

            $chamados[] = [
                'title' => "Chamado {$i}",
                'description' => 'Descrição.',
                'occurrence_type_id' => 1 + $i % 8,
                'resident_id' => $primeiro + $i % self::MORADORES,
                'unit_id' => 1 + $i % self::UNIDADES,
                'assigned_employee_id' => in_array($status, [OccurrenceStatus::Open, OccurrenceStatus::Analysis], true) ? null : $primeiro + self::MORADORES + $i % 10,
                'status' => $status->value,
                'resolved' => $status === OccurrenceStatus::Completed ? true : null,
                'completed_at' => $status === OccurrenceStatus::Completed ? $abertura->copy()->addHours(5)->toDateTimeString() : null,
                'created_at' => $abertura->toDateTimeString(),
                'updated_at' => $abertura->toDateTimeString(),
            ];

            $reservas[] = [
                'common_area_id' => 1 + $i % 5,
                'resident_id' => $primeiro + $i % self::MORADORES,
                // Metade no passado, metade no futuro: a agenda tem o que mostrar.
                'date' => now()->subDays(self::DIAS / 2)->addDays(intdiv($i * self::DIAS, self::LINHAS))->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'status' => $estadosReserva[$i % count($estadosReserva)]->value,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        foreach (array_chunk($chamados, 500) as $lote) {
            DB::table('occurrences')->insert($lote);
        }

        foreach (array_chunk($reservas, 500) as $lote) {
            DB::table('reservations')->insert($lote);
        }

        foreach (array_chunk(range(1, 3000), 500) as $lote) {
            DB::table('logs')->insert(array_map(fn ($i) => [
                'type_log_id' => TypeLogEnum::OCCURRENCE->value, 'user_id' => $primeiro, 'description' => "Ação {$i}", 'ip' => '127.0.0.1', 'created_at' => $agora, 'updated_at' => $agora,
            ], $lote));
        }
    }

    /**
     * Executa a chamada e devolve [milissegundos, consultas].
     */
    private function medir(User $user): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $inicio = hrtime(true);
        $this->actingAs($user)->getJson('/api/dashboard')->assertStatus(201);
        $ms = (hrtime(true) - $inicio) / 1_000_000;

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$ms, $consultas];
    }

    public function test_o_painel_abre_dentro_do_orcamento_e_sem_consulta_por_linha(): void
    {
        $admin = User::factory()->admin()->create();
        $sindico = User::factory()->syndic()->create();

        $this->montarCarga(1);

        // Aquece o que é resolvido uma vez por processo (permissões do Spatie).
        $this->medir($admin);
        $this->medir($sindico);

        [$msAdmin, $consultasAdmin] = $this->medir($admin);
        [$msSindico, $consultasSindico] = $this->medir($sindico);

        // A mesma carga de novo: o dobro de linhas em tudo. Com a base vazia
        // a comparação não serviria — lista vazia não dispara eager load —,
        // então o detector compara duas bases cheias de tamanhos diferentes.
        $this->montarCarga(2);

        // A carga cria um usuário pela factory, e isso limpa o cache de
        // permissões do Spatie: sem aquecer de novo, a recarga dele contaria
        // como consulta do painel.
        $this->medir($admin);
        $this->medir($sindico);

        [, $consultasAdminDobro] = $this->medir($admin);
        [, $consultasSindicoDobro] = $this->medir($sindico);

        fwrite(STDERR, sprintf(
            "\n  Painel com %d chamados e %d reservas\n  administrador %7.1f ms, %d consultas (%d com o dobro de dados)\n  síndico       %7.1f ms, %d consultas (%d com o dobro de dados)\n",
            self::LINHAS, self::LINHAS, $msAdmin, $consultasAdmin, $consultasAdminDobro, $msSindico, $consultasSindico, $consultasSindicoDobro
        ));

        $this->assertLessThan(self::ORCAMENTO_MS, $msAdmin, "O painel do administrador levou {$msAdmin} ms.");
        $this->assertLessThan(self::ORCAMENTO_MS, $msSindico, "O painel do síndico levou {$msSindico} ms.");

        $this->assertSame($consultasAdmin, $consultasAdminDobro, 'O painel do administrador passou a consultar uma vez por linha.');
        $this->assertSame($consultasSindico, $consultasSindicoDobro, 'O painel do síndico passou a consultar uma vez por linha.');
    }
}
