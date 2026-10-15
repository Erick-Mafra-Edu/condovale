<?php

namespace Database\Seeders;

use App\Models\Occurrence;
use Database\Seeders\Concerns\ResolvesReferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Uma ocorrência em cada estado do enum, com e sem funcionário atribuído.
 *
 * É o que permite demonstrar a fila do funcionário, o acompanhamento do
 * morador e o relatório do síndico sem ninguém precisar abrir chamado à mão
 * antes da apresentação.
 */
class OccurrenceSeeder extends Seeder
{
    use ResolvesReferences;

    public function run(): void
    {
        $json = File::get(database_path('seeders/json/occurrences.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'title' => $item->title,
                'description' => $item->description,
                'occurrence_type_id' => $this->occurrenceTypeId($item->occurrence_type_name, 'occurrences.json'),
                'resident_id' => $this->userId($item->resident_email, 'occurrences.json'),
                'unit_id' => $this->unitId($item->unit_code, 'occurrences.json'),
                'assigned_employee_id' => $this->optionalUserId($item->assigned_employee_email, 'occurrences.json'),
                'status' => $item->status,
                'resolved' => $item->resolved,
                'conclusion' => $item->conclusion,
                'completed_at' => $item->completed_days_ago === null
                    ? null
                    : now()->subDays($item->completed_days_ago),
            ];

            Occurrence::create($array);
        }
    }
}
