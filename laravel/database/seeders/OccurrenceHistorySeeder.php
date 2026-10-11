<?php

namespace Database\Seeders;

use App\Models\OccurrenceHistory;
use Database\Seeders\Concerns\ResolvesReferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * A linha do tempo de cada ocorrência, do registro ao desfecho.
 *
 * A ocorrência é citada pelo título porque é o que identifica o chamado para
 * quem lê o arquivo; o id dependeria da ordem das linhas em occurrences.json.
 */
class OccurrenceHistorySeeder extends Seeder
{
    use ResolvesReferences;

    public function run(): void
    {
        $json = File::get(database_path('seeders/json/occurrence_histories.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'occurrence_id' => $this->occurrenceId($item->occurrence_title, 'occurrence_histories.json'),
                'user_id' => $this->userId($item->user_email, 'occurrence_histories.json'),
                'type' => $item->type,
                'message' => $item->message,
                'previous_status' => $item->previous_status,
                'new_status' => $item->new_status,
            ];

            OccurrenceHistory::create($array);
        }
    }
}
