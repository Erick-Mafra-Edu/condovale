<?php

namespace Database\Seeders;

use App\Models\Occurrence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class OccurrenceSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/occurrences.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'title' => $item->title,
                'description' => $item->description,
                'category' => $item->category,
                'resident_id' => $item->resident_id,
                'unit_id' => $item->unit_id,
                'assigned_employee_id' => $item->assigned_employee_id,
                'status' => $item->status,
                'completed_at' => $item->completed_at,
            ];

            Occurrence::create($array);
        }
    }
}
