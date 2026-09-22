<?php

namespace Database\Factories;

use App\Enums\CommonAreaStatus;
use App\Models\CommonArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommonArea>
 */
class CommonAreaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Salão de festas', 'Churrasqueira', 'Quadra', 'Piscina', 'Espaço gourmet']),
            'capacity' => fake()->numberBetween(10, 60),
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'max_reservation_minutes' => 240,
            'requires_approval' => false,
            'status' => CommonAreaStatus::Available,
        ];
    }

    public function requiringApproval(): static
    {
        return $this->state(['requires_approval' => true]);
    }

    public function unavailable(): static
    {
        return $this->state(['status' => CommonAreaStatus::Unavailable]);
    }
}
