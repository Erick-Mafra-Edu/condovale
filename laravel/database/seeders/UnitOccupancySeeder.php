<?php

namespace Database\Seeders;

use App\Models\UnitOccupancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class UnitOccupancySeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/unit_occupancies.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'unit_id' => $item->unit_id,
                'user_id' => $item->user_id,
                'occupant_type' => $item->occupant_type,
                'started_at' => $item->started_at,
                'ended_at' => $item->ended_at,
                'is_active' => $item->is_active,
            ];

            UnitOccupancy::create($array);
        }
    }
}
