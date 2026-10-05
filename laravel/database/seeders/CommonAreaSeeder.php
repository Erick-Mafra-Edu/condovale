<?php

namespace Database\Seeders;

use App\Models\CommonArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CommonAreaSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/common_areas.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'name' => $item->name,
                'description' => $item->description,
                'image_url' => $item->image_url,
                'capacity' => $item->capacity,
                'opening_time' => $item->opening_time,
                'closing_time' => $item->closing_time,
                'max_reservation_minutes' => $item->max_reservation_minutes,
                'requires_approval' => $item->requires_approval,
                'status' => $item->status,
            ];

            CommonArea::create($array);
        }
    }
}
