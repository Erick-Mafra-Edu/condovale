<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'common_area_id' => CommonArea::factory(),
            'resident_id' => User::factory()->resident(),
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => ReservationStatus::Approved,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => ReservationStatus::Pending]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => ReservationStatus::Cancelled]);
    }

    /** Reserva de diária: ocupa o dia inteiro da área. */
    public function fullDay(): static
    {
        return $this->state(['start_time' => null, 'end_time' => null]);
    }
}
