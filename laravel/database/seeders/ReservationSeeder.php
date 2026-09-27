<?php

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $salao = CommonArea::where('name', 'Salão de Festas Principal')->first();
        $churrasqueira = CommonArea::where('name', 'Churrasqueira Externa')->first();
        $quadra = CommonArea::where('name', 'Quadra Poliesportiva')->first();

        $morador1 = User::where('email', 'morador1@condovale.com')->first();
        $morador2 = User::where('email', 'morador2@condovale.com')->first();

        if ($salao && $morador1) {
            Reservation::firstOrCreate(
                [
                    'common_area_id' => $salao->id,
                    'resident_id' => $morador1->id,
                    'date' => now()->addDays(5)->format('Y-m-d'),
                ],
                [
                    'start_time' => '12:00:00',
                    'end_time' => '18:00:00',
                    'status' => ReservationStatus::Approved,
                ]
            );
        }

        if ($churrasqueira && $morador2) {
            Reservation::firstOrCreate(
                [
                    'common_area_id' => $churrasqueira->id,
                    'resident_id' => $morador2->id,
                    'date' => now()->addDays(2)->format('Y-m-d'),
                ],
                [
                    'start_time' => '12:00:00',
                    'end_time' => '16:00:00',
                    'status' => ReservationStatus::Pending,
                ]
            );
        }

        if ($quadra && $morador1) {
            Reservation::firstOrCreate(
                [
                    'common_area_id' => $quadra->id,
                    'resident_id' => $morador1->id,
                    'date' => now()->addDay()->format('Y-m-d'),
                ],
                [
                    'start_time' => '18:00:00',
                    'end_time' => '20:00:00',
                    'status' => ReservationStatus::Approved,
                ]
            );
        }
    }
}
