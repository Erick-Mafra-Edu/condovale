<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['block' => 'Bloco A', 'number' => '101', 'code' => 'A-101'],
            ['block' => 'Bloco A', 'number' => '102', 'code' => 'A-102'],
            ['block' => 'Bloco A', 'number' => '201', 'code' => 'A-201'],
            ['block' => 'Bloco A', 'number' => '202', 'code' => 'A-202'],
            ['block' => 'Bloco B', 'number' => '101', 'code' => 'B-101'],
            ['block' => 'Bloco B', 'number' => '102', 'code' => 'B-102'],
            ['block' => 'Bloco B', 'number' => '201', 'code' => 'B-201'],
            ['block' => 'Bloco B', 'number' => '202', 'code' => 'B-202'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }
    }
}
