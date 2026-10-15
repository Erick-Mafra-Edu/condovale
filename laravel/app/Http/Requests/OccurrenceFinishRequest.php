<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF04 / RN06 — encerrar o chamado exige dizer como terminou.
 *
 * Encerrar não é o mesmo que resolver: a peça pode não existir, o problema
 * pode ser da concessionária, o vizinho pode não ter atendido. O relatório do
 * síndico precisa separar o que foi resolvido do que só foi fechado, e o
 * morador precisa saber o que aconteceu com o pedido dele — por isso as duas
 * informações são obrigatórias.
 */
class OccurrenceFinishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolved' => 'required|boolean',
            'conclusion' => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'resolved.required' => 'Informe se o problema foi resolvido.',
            'resolved.boolean' => 'Informe se o problema foi resolvido com verdadeiro ou falso.',
            'conclusion.required' => 'Descreva a conclusão do atendimento.',
            'conclusion.max' => 'A conclusão deve ter no máximo 2000 caracteres.',
        ];
    }
}
