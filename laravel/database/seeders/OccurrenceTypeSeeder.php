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
                'active' => $item->active,
            ];

            OccurrenceType::create($array);
        }
    }
}
