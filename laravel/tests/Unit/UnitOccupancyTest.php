<?php

namespace Tests\Unit;

use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitOccupancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracks_unit_occupancy_history_when_resident_changes_units(): void
    {
        $unit1 = Unit::create(['block' => 'A', 'number' => '101', 'code' => 'A-101']);
        $unit2 = Unit::create(['block' => 'B', 'number' => '202', 'code' => 'B-202']);

        $user = User::factory()->create(['unit_id' => $unit1->id]);

        $firstOccupancy = UnitOccupancy::create([
            'unit_id' => $unit1->id,
            'user_id' => $user->id,
            'occupant_type' => 'tenant',
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => now()->subDay()->toDateString(),
            'is_active' => false,
        ]);

        $secondOccupancy = UnitOccupancy::create([
            'unit_id' => $unit2->id,
            'user_id' => $user->id,
            'occupant_type' => 'owner',
            'started_at' => now()->toDateString(),
            'ended_at' => null,
            'is_active' => true,
        ]);

        $user->update(['unit_id' => $unit2->id]);

        $this->assertCount(2, $user->unitOccupancies);
        $this->assertEquals($unit1->id, $firstOccupancy->unit_id);
        $this->assertEquals($unit2->id, $secondOccupancy->unit_id);
        $this->assertFalse($firstOccupancy->is_active);
        $this->assertTrue($secondOccupancy->is_active);
    }
}
