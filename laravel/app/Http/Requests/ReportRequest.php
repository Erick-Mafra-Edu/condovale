<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF06 — o que todo relatório recebe: período, paginação e exportação.
 *
 * Cada relatório herda daqui e acrescenta os próprios filtros, para que o de
 * ocorrências e o de reservas aceitem o período, a página e a exportação do
 * mesmo jeito. O período é obrigatório porque é ele que limita a consulta: um
 * relatório sem data é a tabela inteira.
 *
 * `export` aceita "true" e "false" além de 1 e 0, porque é assim que o
 * navegador manda um booleano na query string, e a regra `boolean` do Laravel
 * recusaria.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:'.DefaultPaginationRequest::LIMITE_MAXIMO,
            'export' => 'nullable|in:0,1,true,false',
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'Informe a data inicial do período.',
            'from.date_format' => 'A data inicial deve estar no formato AAAA-MM-DD.',
            'to.required' => 'Informe a data final do período.',
            'to.date_format' => 'A data final deve estar no formato AAAA-MM-DD.',
            'to.after_or_equal' => 'A data final não pode ser anterior à data inicial.',
            'page.integer' => 'A página deve ser um número inteiro.',
            'page.min' => 'A página deve ser 1 ou maior.',
            'limit.integer' => 'O tamanho da página deve ser um número inteiro.',
            'limit.min' => 'O tamanho da página deve ser de pelo menos 1 registro.',
            'limit.max' => 'O tamanho da página não pode passar de '.DefaultPaginationRequest::LIMITE_MAXIMO.' registros.',
            'export.in' => 'Informe a exportação com verdadeiro ou falso.',
        ];
    }
}
