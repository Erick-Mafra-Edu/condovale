<?php

namespace Database\Seeders;

use App\Models\Log;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LogSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/logs.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'type_log_id' => $item->type_log_id,
                'user_id' => $item->user_id,
                'description' => $item->description,
                'ip' => $item->ip,
                'data_log' => $item->data_log,
            ];

            Log::create($array);
        }
    }
}
