<?php

namespace Database\Seeders;

use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Database\Seeder;

class UnitOccupancySeeder extends Seeder
{
    public function run(): void
    {
        $morador1 = User::where('email', 'morador1@condovale.com')->first();
        $morador2 = User::where('email', 'morador2@condovale.com')->first();
        $morador3 = User::where('email', 'morador3@condovale.com')->first();

        if ($morador1 && $morador1->unit_id) {
            UnitOccupancy::firstOrCreate(
                ['user_id' => $morador1->id, 'unit_id' => $morador1->unit_id],
                [
                    'occupant_type' => 'owner',
                    'started_at' => now()->subMonths(6)->toDateString(),
                    'ended_at' => null,
                    'is_active' => true,
                ]
            );
        }

        if ($morador2 && $morador2->unit_id) {
            UnitOccupancy::firstOrCreate(
                ['user_id' => $morador2->id, 'unit_id' => $morador2->unit_id],
                [
                    'occupant_type' => 'tenant',
                    'started_at' => now()->subYear()->toDateString(),
                    'ended_at' => null,
                    'is_active' => true,
                ]
            );
        }

        if ($morador3 && $morador3->unit_id) {
            UnitOccupancy::firstOrCreate(
                ['user_id' => $morador3->id, 'unit_id' => $morador3->unit_id],
                [
                    'occupant_type' => 'owner',
                    'started_at' => now()->subMonths(3)->toDateString(),
                    'ended_at' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
