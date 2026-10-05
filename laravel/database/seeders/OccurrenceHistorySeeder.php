<?php

namespace Database\Seeders;

use App\Models\OccurrenceHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class OccurrenceHistorySeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/occurrence_histories.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'occurrence_id' => $item->occurrence_id,
                'user_id' => $item->user_id,
                'type' => $item->type,
                'message' => $item->message,
                'previous_status' => $item->previous_status,
                'new_status' => $item->new_status,
            ];

            OccurrenceHistory::create($array);
        }
    }
}
