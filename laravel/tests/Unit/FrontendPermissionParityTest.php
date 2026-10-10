<?php

namespace Tests\Unit;

use App\Enums\UseCase;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * Compara a matriz do backend com o arquivo do frontend, lendo-o de verdade.
 *
 * O PermissionMatrixTest guarda o enum contra uma cópia escrita dentro do
 * próprio teste — ou seja, compara o enum consigo mesmo. Ele não enxergou duas
 * divergências reais que entraram pelo frontend: um caso de uso que só existe
 * lá, e um que foi acrescentado aqui e nunca chegou lá.
 *
 * Divergência não quebra nada visivelmente: a interface oferece uma ação que a
 * API recusa, ou esconde uma que ela aceitaria, e a pessoa vê um 403 sem
 * explicação ou um botão que nunca aparece. Esta é a trava que faltava.
 *
 * O teste **lê** o arquivo do frontend; nada aqui o altera.
 */
class FrontendPermissionParityTest extends TestCase
{
    private const ARQUIVO = __DIR__.'/../../../frontend/app/domain/permissions.ts';

    /**
     * Divergências que já existem e aguardam alteração no frontend.
     *
     * Elas estão aqui, e não escondidas, para o teste poder passar sem deixar
     * de vigiar: qualquer divergência **nova** quebra a suíte. Riscar uma
     * linha desta lista é o que fecha a pendência.
     *
     * A lista é **por papel**, de propósito. Perdoar um caso de uso em todos
     * os papéis de uma vez deixaria de vigiá-lo justamente onde ele ainda
     * importa — `manage-units` precisa ser perdoado no síndico e continuar
     * conferido no administrador.
     *
     * @var array<string, list<string>>
     */
    private const DIVERGENCIAS_POR_PAPEL = [
        // RN03 deu as alterações estruturais de unidades ao síndico; o
        // frontend ainda as dá só ao administrador.
        'syndic' => ['manage-units', 'update-own-profile'],
        // update-own-profile passou a valer para os quatro perfis aqui, e o
        // frontend ainda o dá apenas ao morador.
        'employee' => ['update-own-profile'],
        'admin' => ['update-own-profile', 'manage-users'],
        'resident' => [],
    ];

    /**
     * Casos de uso que existem de um lado só.
     *
     * `manage-users` foi acrescentado ao frontend pelo trabalho de gestão de
     * usuários e não existe no backend. Enquanto não existir, uma rota
     * protegida por ele responderia 403, porque a permissão não está no banco.
     * Falta decidir se é outro nome para `manage-residents` ou um caso de uso
     * distinto.
     *
     * @var list<string>
     */
    private const CASOS_SO_DE_UM_LADO = [
        'manage-users',
    ];

    private function fonte(): string
    {
        if (! is_file(self::ARQUIVO)) {
            $this->markTestSkipped('frontend/app/domain/permissions.ts não está presente nesta árvore.');
        }

        // Normaliza as quebras de linha: o arquivo vem com CRLF, e uma
        // expressão ancorada em "\n\n" não casaria nada — o teste passaria a
        // acusar divergência em tudo, que é a falha mais inútil possível.
        return str_replace("\r\n", "\n", file_get_contents(self::ARQUIVO));
    }

    /**
     * Os casos de uso declarados no tipo `UseCase` do frontend.
     *
     * @return list<string>
     */
    private function casosDoFrontend(): array
    {
        preg_match('/export type UseCase =(.*?)\n\n/s', $this->fonte(), $bloco);

        preg_match_all("/'([a-z-]+)'/", $bloco[1] ?? '', $achados);

        return $achados[1];
    }

    /**
     * O conjunto de um papel dentro de `rolePermissions`.
     *
     * @return list<string>
     */
    private function casosDoPapel(string $papel): array
    {
        preg_match("/{$papel}: new Set\(\[(.*?)\]\)/s", $this->fonte(), $bloco);

        preg_match_all("/'([a-z-]+)'/", $bloco[1] ?? '', $achados);

        return $achados[1];
    }

    public function test_os_casos_de_uso_sao_os_mesmos_dos_dois_lados(): void
    {
        $frontend = $this->casosDoFrontend();
        $backend = array_map(fn (UseCase $useCase) => $useCase->value, UseCase::cases());

        sort($frontend);
        sort($backend);

        $soNoFrontend = array_diff($frontend, $backend, self::CASOS_SO_DE_UM_LADO);
        $soNoBackend = array_diff($backend, $frontend, self::CASOS_SO_DE_UM_LADO);

        $this->assertSame([], array_values($soNoFrontend), 'Casos de uso que só existem no frontend: '.implode(', ', $soNoFrontend).'. Um deles numa rota responderia 403, porque a permissão não existe no banco.');
        $this->assertSame([], array_values($soNoBackend), 'Casos de uso que só existem no backend: '.implode(', ', $soNoBackend).'. A interface esconde o que a API aceitaria.');
    }

    public function test_cada_papel_tem_a_mesma_matriz_dos_dois_lados(): void
    {
        foreach (UserRole::cases() as $papel) {
            $perdoados = self::DIVERGENCIAS_POR_PAPEL[$papel->value] ?? [];

            $frontend = array_values(array_diff($this->casosDoPapel($papel->value), $perdoados));
            $backend = array_values(array_diff(
                array_map(fn (UseCase $useCase) => $useCase->value, $papel->useCases()),
                $perdoados
            ));

            sort($frontend);
            sort($backend);

            $this->assertSame(
                $backend,
                $frontend,
                "A matriz do papel {$papel->value} divergiu, e a diferenca nao esta na lista de divergencias conhecidas."
            );
        }
    }
}
