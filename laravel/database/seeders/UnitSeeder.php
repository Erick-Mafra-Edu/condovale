<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/units.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'block' => $item->block,
                'number' => $item->number,
                'code' => $item->code,
                'status' => $item->status,
            ];

            Unit::create($array);
        }
    }
}
