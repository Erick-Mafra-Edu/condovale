<?php

namespace Database\Seeders;

use App\Models\OccurrenceType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Os tipos com que o condomínio classifica os chamados.
 *
 * É carga inicial, não lista fechada: a administração acrescenta, edita e
 * aposenta tipos pela própria aplicação. O que entra aqui é o conjunto com que
 * o sistema começa a funcionar no primeiro dia.
 */
class OccurrenceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/occurrence_types.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'name' => $item->name,
                'description' => $item->description,
                'kind' => $item->kind,
                'employee_can_close' => $item->employee_can_close,
                'active' => $item->active,
            ];

            // Pelo nome, a chave de negócio: a carga pode ser reaplicada sem
            // duplicar tipos, e uma natureza corrigida no JSON chega ao banco.
            OccurrenceType::updateOrCreate(['name' => $item->name], $array);
        }
    }
}
