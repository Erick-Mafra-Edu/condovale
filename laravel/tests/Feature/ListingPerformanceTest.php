<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UC-T01 / RNF01 — as listagens respondem abaixo de 2 segundos sob carga padrão.
 *
 * O requisito falava em "carga padrão" sem dizer qual, e a conformidade do
 * frontend registrava isso como decisão pendente. A carga está fixada nas
 * constantes abaixo, dimensionada por um condomínio de porte médio com alguns
 * anos de uso, e é ela que este teste monta antes de medir.
 *
 * O que este teste mede e o que não mede: ele roda em SQLite, então os
 * milissegundos daqui não preveem o tempo do MySQL da hospedagem — o tempo de
 * ida e volta real foi medido à parte, contra o servidor de produção. O que
 * ele prende é o que faz uma listagem passar de dois segundos e que nenhum
 * hardware conserta: consulta por linha, página sem teto e paginação sem
 * ordem definida.
 */
class ListingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** Carga padrão da UC-T01. */
    private const UNIDADES = 500;

    private const USUARIOS = 1200;

    private const VINCULOS = 2000;

    private const RESERVAS = 10000;

    private const AREAS_COMUNS = 5;

    /** O teto da RNF01, em milissegundos. */
    private const ORCAMENTO_MS = 2000;

    private function montarCargaPadrao(): void
    {
        $agora = now()->toDateTimeString();

        $unidades = [];

        for ($i = 1; $i <= self::UNIDADES; $i++) {
            $bloco = chr(65 + intdiv($i - 1, 100));
            $unidades[] = [
                'block' => "Bloco {$bloco}",
                'number' => (string) (100 + $i),
                'code' => "{$bloco}-".(100 + $i),
                'status' => $i % 50 === 0 ? UnitStatus::Inactive->value : UnitStatus::Active->value,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        foreach (array_chunk($unidades, 200) as $lote) {
            DB::table('units')->insert($lote);
        }

        // Um único hash, reaproveitado: o custo do Argon2id é proposital no
        // login e irrelevante para medir listagem.
        $hash = User::factory()->resident()->create(['email' => 'semente@condovale.test'])->password;

        $usuarios = [];

        for ($i = 1; $i <= self::USUARIOS; $i++) {
            $usuarios[] = [
                'name' => 'Morador '.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'email' => "carga{$i}@condovale.test",
                'password' => $hash,
                'role' => UserRole::Resident->value,
                'status' => $i % 25 === 0 ? UserStatus::Inactive->value : UserStatus::Active->value,
                'unit_id' => (($i - 1) % self::UNIDADES) + 1,
                'must_change_password' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        foreach (array_chunk($usuarios, 200) as $lote) {
            DB::table('users')->insert($lote);
        }

        $primeiroId = DB::table('users')->where('email', 'carga1@condovale.test')->value('id');

        $vinculos = [];

        for ($i = 0; $i < self::VINCULOS; $i++) {
            $ativo = $i < self::USUARIOS;

            $vinculos[] = [
                'unit_id' => ($i % self::UNIDADES) + 1,
                'user_id' => $primeiroId + ($i % self::USUARIOS),
                'occupant_type' => $i % 3 === 0 ? 'owner' : 'tenant',
                'started_at' => now()->subYears(2)->toDateString(),
                'ended_at' => $ativo ? null : now()->subYear()->toDateString(),
                'is_active' => $ativo,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        foreach (array_chunk($vinculos, 200) as $lote) {
            DB::table('unit_occupancies')->insert($lote);
        }

        $areas = [];

        for ($i = 1; $i <= self::AREAS_COMUNS; $i++) {
            $areas[] = [
                'name' => "Área comum {$i}",
                'description' => 'Espaço de uso coletivo.',
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

        $estados = ReservationStatus::cases();
        $reservas = [];

        for ($i = 0; $i < self::RESERVAS; $i++) {
            $reservas[] = [
                'common_area_id' => ($i % self::AREAS_COMUNS) + 1,
                'resident_id' => $primeiroId + ($i % self::USUARIOS),
                'date' => now()->subDays(1000)->addDays(intdiv($i, 10))->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
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
     * Executa a chamada e devolve [milissegundos, consultas, resultado].
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

    private function relatar(string $caso, float $ms, string $extra = ''): void
    {
        fwrite(STDERR, sprintf("  %-46s %7.1f ms  %s\n", $caso, $ms, $extra));
    }

    private function consultaReservas(array $parametros, int $limite = 10)
    {
        $request = Request::create('/api/reservations', 'GET', $parametros);

        return HandlePaginationAction::execute($request, new Reservation, ['status'])->paginate($limite);
    }

    public function test_as_listagens_de_moradores_respondem_dentro_do_orcamento(): void
    {
        $this->montarCargaPadrao();

        $admin = User::factory()->admin()->create();

        $casos = [
            'GET /users' => '/api/users',
            'GET /users?limit=100' => '/api/users?limit=100',
            'GET /users?search=Morador 004' => '/api/users?search=Morador+004',
            'GET /users?sort=name&direction=desc' => '/api/users?sort=name&direction=desc',
            'GET /users?filters=status:=:active' => '/api/users?filters=status:=:active',
            'GET /users?relations=unit' => '/api/users?relations=unit',
            'GET /users (última página)' => '/api/users?limit=100&page=12',
            'GET /unit-occupancies' => '/api/unit-occupancies',
            'GET /unit-occupancies?relations=user;unit' => '/api/unit-occupancies?relations=user;unit',
            'GET /unit-occupancies?filters=is_active:=:1' => '/api/unit-occupancies?filters=is_active:=:1',
        ];

        fwrite(STDERR, "\n  Carga padrão: ".self::UNIDADES.' unidades, '.self::USUARIOS.' usuários, '
            .self::VINCULOS.' vínculos, '.self::RESERVAS." reservas\n");

        foreach ($casos as $caso => $url) {
            [$ms, , $resposta] = $this->medir(
                fn () => $this->actingAs($admin)->getJson($url)
            );

            $this->relatar($caso, $ms);

            $resposta->assertStatus(201);

            $this->assertLessThan(
                self::ORCAMENTO_MS,
                $ms,
                "{$caso} levou {$ms} ms, acima do teto de ".self::ORCAMENTO_MS.' ms da RNF01.'
            );
        }
    }

    public function test_a_listagem_de_reservas_responde_dentro_do_orcamento(): void
    {
        // As reservas ainda não têm rota: o módulo é de outro card. O que se
        // mede aqui é a consulta que a listagem fará, montada exatamente como
        // o controller a montará — pelo HandlePaginationAction.
        $this->montarCargaPadrao();

        $casos = [
            'reservas, primeira página' => [],
            'reservas por área, mais recentes' => ['filters' => 'common_area_id:=:1', 'sort' => 'date', 'direction' => 'desc'],
            'reservas pendentes' => ['filters' => 'status:=:pending'],
            'reservas com área e morador' => ['relations' => 'commonArea;resident'],
            'reservas de um morador' => ['filters' => 'resident_id:=:2'],
            'reservas, página distante' => ['page' => '900'],
        ];

        fwrite(STDERR, "\n");

        foreach ($casos as $caso => $parametros) {
            [$ms, , $pagina] = $this->medir(fn () => $this->consultaReservas($parametros));

            $this->relatar($caso, $ms, $pagina->total().' no total');

            $this->assertLessThan(
                self::ORCAMENTO_MS,
                $ms,
                "{$caso} levou {$ms} ms, acima do teto de ".self::ORCAMENTO_MS.' ms da RNF01.'
            );
        }
    }

    public function test_nenhuma_listagem_consulta_uma_vez_por_linha(): void
    {
        // O detector de N+1 que não depende do framework: a mesma listagem,
        // com 50 e com 100 linhas, precisa disparar o mesmo número de
        // consultas. Contar em termos absolutos não serviria — sessão e
        // permissões entram na conta e mudam quando o middleware muda. O que
        // não pode mudar é a contagem crescer com o tamanho da página.
        //
        // A comparação é entre duas páginas grandes, e não entre uma linha e
        // cem, porque com uma só linha uma relação nula não dispara eager load
        // nenhum: a contagem subiria de 2 para 3 ao crescer a página e o teste
        // acusaria N+1 onde há apenas carregamento antecipado. Entre 50 e 100
        // o eager load roda dos dois lados, e consulta por linha apareceria
        // como uma diferença de cinquenta.
        $this->montarCargaPadrao();

        $admin = User::factory()->admin()->create();

        $listagens = [
            'GET /users' => '/api/users',
            'GET /users?relations=unit' => '/api/users?relations=unit',
            'GET /unit-occupancies' => '/api/unit-occupancies',
            'GET /unit-occupancies?relations=user;unit' => '/api/unit-occupancies?relations=user;unit',
        ];

        fwrite(STDERR, "\n");

        foreach ($listagens as $caso => $url) {
            $juntar = str_contains($url, '?') ? '&' : '?';

            // Fora da conta: aquece o que é resolvido uma vez por processo — a
            // listagem de colunas da tabela e o cache de permissões do Spatie.
            $this->actingAs($admin)->getJson($url.$juntar.'limit=50')->assertStatus(201);

            [, $comCinquenta] = $this->medir(
                fn () => $this->actingAs($admin)->getJson($url.$juntar.'limit=50')->assertStatus(201)
            );

            [$ms, $comCem] = $this->medir(
                fn () => $this->actingAs($admin)->getJson($url.$juntar.'limit=100')->assertStatus(201)
            );

            $this->relatar($caso, $ms, "{$comCinquenta} consultas com 50 linhas, {$comCem} com 100");

            $this->assertSame(
                $comCinquenta,
                $comCem,
                "{$caso} passou de {$comCinquenta} para {$comCem} consultas ao dobrar a página: há consulta por linha."
            );
        }
    }

    public function test_a_listagem_de_reservas_nao_consulta_uma_vez_por_linha(): void
    {
        $this->montarCargaPadrao();

        $parametros = ['relations' => 'commonArea;resident'];

        // Aquece a listagem de colunas da tabela, resolvida uma vez por processo.
        $this->consultaReservas($parametros, 50);

        [, $comCinquenta] = $this->medir(fn () => $this->consultaReservas($parametros, 50));
        [$ms, $comCem] = $this->medir(fn () => $this->consultaReservas($parametros, 100));

        fwrite(STDERR, "\n");
        $this->relatar('reservas com relações', $ms, "{$comCinquenta} consultas com 50 linhas, {$comCem} com 100");

        $this->assertSame(
            $comCinquenta,
            $comCem,
            "A listagem de reservas passou de {$comCinquenta} para {$comCem} consultas ao dobrar a página: há consulta por linha."
        );
    }

    public function test_o_tamanho_da_pagina_tem_teto(): void
    {
        // Era o único parâmetro capaz de estourar o orçamento sozinho, e vindo
        // de fora: sem teto, `limit=999999` carregava a tabela inteira na
        // memória e a serializava numa resposta só.
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/users?limit=999999')
            ->assertStatus(422)
            ->assertJsonPath('errors.limit.0', 'O tamanho da página não pode passar de 100 registros.');

        $this->actingAs($admin)
            ->getJson('/api/users?limit='.DefaultPaginationRequest::LIMITE_MAXIMO)
            ->assertStatus(201);
    }

    public function test_tamanho_de_pagina_invalido_nao_derruba_a_requisicao(): void
    {
        // `limit=-5` chegava ao banco como LIMIT negativo e devolvia 500.
        $admin = User::factory()->admin()->create();

        foreach (['-5', '0'] as $limite) {
            $this->actingAs($admin)
                ->getJson("/api/users?limit={$limite}")
                ->assertStatus(422)
                ->assertJsonPath('errors.limit.0', 'O tamanho da página deve ser de pelo menos 1 registro.');
        }

        $this->actingAs($admin)->getJson('/api/users?page=-1')->assertStatus(422);
    }

    public function test_a_paginacao_nao_repete_nem_pula_registros(): void
    {
        // Sem ORDER BY, a ordem das linhas é a que o banco achar conveniente, e
        // ela muda conforme o índice escolhido para o filtro. Paginar sobre
        // isso devolve a mesma linha em duas páginas e nenhuma linha de alguma
        // outra, sem erro em lugar nenhum.
        $this->montarCargaPadrao();

        $admin = User::factory()->admin()->create();

        $vistos = [];

        foreach ([1, 2, 3, 4, 5] as $pagina) {
            $resposta = $this->actingAs($admin)
                ->getJson("/api/users?limit=100&page={$pagina}")
                ->assertStatus(201);

            $vistos = array_merge($vistos, array_column($resposta->json('data'), 'id'));
        }

        $this->assertCount(500, $vistos);
        $this->assertSame($vistos, array_values(array_unique($vistos)), 'A paginação devolveu o mesmo registro em mais de uma página.');
    }

    public function test_a_ordenacao_por_nome_desempata_pela_chave(): void
    {
        // Ordenar por coluna não única deixa os empatados em ordem livre, e o
        // problema de paginação volta entre eles. A chave primária entra como
        // desempate sempre.
        $admin = User::factory()->admin()->create();

        foreach (['Ana Souza', 'Ana Souza', 'Ana Souza'] as $nome) {
            User::factory()->resident()->create(['name' => $nome]);
        }

        $primeira = $this->actingAs($admin)->getJson('/api/users?sort=name&limit=2&page=1')->json('data');
        $segunda = $this->actingAs($admin)->getJson('/api/users?sort=name&limit=2&page=2')->json('data');

        $ids = array_merge(array_column($primeira, 'id'), array_column($segunda, 'id'));

        $this->assertSame($ids, array_values(array_unique($ids)));
    }
}
