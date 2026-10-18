<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF04 / RN06 — finalizar o chamado: o id vem na rota, e o corpo diz o que foi
 * feito.
 *
 * Só a conclusão é obrigatória. O status não vem da requisição — finalizar
 * leva o chamado a Concluído sempre, e quem decide isso é o backend.
 *
 * `resolved` é opcional e vale verdadeiro quando não vem: quem descreve o que
 * fez para finalizar, na regra, resolveu. Ele existe para a exceção — a peça
 * que não existe, o problema que é da concessionária —, porque o relatório do
 * síndico precisa separar o que foi resolvido do que só foi fechado.
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
            'conclusion' => 'required|string|max:2000',
            'resolved' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'conclusion.required' => 'Descreva o que foi feito para finalizar o atendimento.',
            'conclusion.max' => 'A conclusão deve ter no máximo 2000 caracteres.',
            'resolved.boolean' => 'Informe se o problema foi resolvido com verdadeiro ou falso.',
        ];
    }
}
