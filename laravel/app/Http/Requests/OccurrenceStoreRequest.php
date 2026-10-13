<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF04 / RN05 — o chamado tem descrição textual, um tipo e um morador.
 *
 * `resident_id` é opcional e só vale para quem abre chamado no lugar de
 * outra pessoa — síndico e administrador. Quem não o envia está abrindo para
 * si, e aí o morador vem da sessão, nunca do corpo da requisição. Quem o envia
 * sem ter o caso de uso recebe 403 da Action, não daqui: isto aqui valida
 * formato, não autorização.
 */
class OccurrenceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:2000',
            // `active,1` recusa tipo aposentado: ele continua válido nos
            // chamados antigos, mas não entra em chamado novo.
            'occurrence_type_id' => 'required|integer|exists:occurrence_types,id,active,1',
            'resident_id' => 'sometimes|integer|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o título da ocorrência.',
            'title.max' => 'O título deve ter no máximo 120 caracteres.',
            'description.required' => 'Descreva a ocorrência.',
            'description.max' => 'A descrição deve ter no máximo 2000 caracteres.',
            'occurrence_type_id.required' => 'Informe o tipo da ocorrência.',
            'occurrence_type_id.exists' => 'O tipo de ocorrência informado não existe ou está inativo.',
            'resident_id.exists' => 'O morador informado não existe.',
        ];
    }
}
