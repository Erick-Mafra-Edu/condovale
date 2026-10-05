<?php

namespace Database\Seeders;

use App\Models\Reservation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/reservations.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'common_area_id' => $item->common_area_id,
                'resident_id' => $item->resident_id,
                'date' => $item->date,
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
                'status' => $item->status,
                'rejection_reason' => $item->rejection_reason,
            ];

            Reservation::create($array);
        }
    }
}
