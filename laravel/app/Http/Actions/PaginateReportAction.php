<?php

namespace App\Http\Actions;

use App\Exceptions\BusinessRuleException;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * RF06 — a lista de um relatório, paginada ou inteira para exportar.
 *
 * Os relatórios de ocorrências e de reservas devolvem a lista no mesmo
 * formato, e a tela trata os dois do mesmo jeito. Ter a paginação e o teto de
 * exportação num lugar só impede que um relatório ganhe uma regra que o outro
 * não tem.
 */
class PaginateReportAction
{
    /**
     * Na exportação vem tudo (RN12), até o teto informado. O teto é conferido
     * pela contagem, antes de montar qualquer linha: a hospedagem corta o
     * script em 60 segundos, e uma exportação de anos morreria no meio.
     *
     * @param  array{page?: int|null, limit?: int|null, export?: bool}  $filters
     * @param  Closure(mixed): array<string, mixed>  $serialize  uma linha do model para o array da resposta
     * @param  string  $itens  o nome do que está sendo contado, para a mensagem de recusa
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function execute(Builder $query, array $filters, int $teto, Closure $serialize, string $itens): array
    {
        if ($filters['export'] ?? false) {
            $total = (clone $query)->count();

            if ($total > $teto) {
                throw BusinessRuleException::unprocessable(
                    "O período tem {$total} {$itens}, acima do máximo de {$teto} por exportação. Reduza o período ou use os filtros."
                );
            }

            return [
                'data' => $query->get()->map($serialize)->all(),
                'total' => $total,
                'page' => 1,
                'per_page' => $total,
                'last_page' => 1,
            ];
        }

        $pagina = $query->paginate($filters['limit'] ?? 10, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'data' => $pagina->getCollection()->map($serialize)->all(),
            'total' => $pagina->total(),
            'page' => $pagina->currentPage(),
            'per_page' => $pagina->perPage(),
            'last_page' => $pagina->lastPage(),
        ];
    }
}
