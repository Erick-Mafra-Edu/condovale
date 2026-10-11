<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query contract of every listing, consumed by HandlePaginationAction.
 */
class DefaultPaginationRequest extends FormRequest
{
    /**
     * Quantas linhas uma página pode devolver (UC-T01 / RNF01).
     *
     * Sem teto, `?limit=999999` fazia a listagem carregar a tabela inteira na
     * memória e serializá-la numa resposta só — com a carga padrão são
     * milhares de linhas, e nenhum índice salva disso. Era o único parâmetro
     * capaz de estourar o orçamento de dois segundos sozinho, e vindo de fora.
     *
     * Quem precisa de mais que isto pagina: é para isso que `page` existe.
     */
    public const LIMITE_MAXIMO = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attributes' => 'nullable|string',
            'direction' => 'nullable|string',
            'search' => 'nullable|string',
            // `min:1` também fecha um erro 500: `limit=-5` chegava ao banco
            // como LIMIT negativo e derrubava a requisição.
            'limit' => 'nullable|integer|min:1|max:'.self::LIMITE_MAXIMO,
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|string',
            'filters' => 'nullable|string',
            'filtersOr' => 'nullable|string',
            'filtersIn' => 'nullable|string',
            'relations' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'limit.integer' => 'O tamanho da página deve ser um número inteiro.',
            'limit.min' => 'O tamanho da página deve ser de pelo menos 1 registro.',
            'limit.max' => 'O tamanho da página não pode passar de '.self::LIMITE_MAXIMO.' registros.',
            'page.integer' => 'A página deve ser um número inteiro.',
            'page.min' => 'A página deve ser 1 ou maior.',
        ];
    }
}
