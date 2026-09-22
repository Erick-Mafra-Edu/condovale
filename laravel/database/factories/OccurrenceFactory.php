<?php

namespace Database\Factories;

use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Occurrence>
 */
class OccurrenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Manutenção', 'Segurança', 'Conservação', 'Convivência']),
            'resident_id' => User::factory()->resident(),
            'status' => OccurrenceStatus::Open,
        ];
    }

    public function assignedTo(User $employee): static
    {
        return $this->state([
            'assigned_employee_id' => $employee->id,
            'status' => OccurrenceStatus::Assigned,
        ]);
    }

    public function inProgressWith(User $employee): static
    {
        return $this->state([
            'assigned_employee_id' => $employee->id,
            'status' => OccurrenceStatus::InProgress,
        ]);
    }
}
