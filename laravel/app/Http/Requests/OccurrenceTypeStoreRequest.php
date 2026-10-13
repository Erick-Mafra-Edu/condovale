<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OccurrenceTypeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // O nome é a chave de negócio: é por ele que a carga referencia o
            // tipo e é ele que aparece no relatório. Dois tipos com o mesmo
            // nome fariam a contagem do síndico dividir o mesmo assunto em
            // duas linhas.
            'name' => 'required|string|max:60|unique:occurrence_types,name',
            'description' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do tipo de ocorrência.',
            'name.unique' => 'Já existe um tipo de ocorrência com este nome.',
            'name.max' => 'O nome do tipo deve ter no máximo 60 caracteres.',
            'description.max' => 'A descrição deve ter no máximo 255 caracteres.',
        ];
    }
}
