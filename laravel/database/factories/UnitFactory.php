<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $letra = fake()->randomElement(['A', 'B', 'C']);
        // O número é único na fábrica, e é ele que torna o código único: a
        // coluna `code` tem unicidade no banco, e duas unidades sorteadas
        // iguais fariam o teste falhar por colisão em vez de pela regra.
        $numero = (string) fake()->unique()->numberBetween(101, 9999);

        return [
            'block' => "Bloco {$letra}",
            'number' => $numero,
            'code' => "{$letra}-{$numero}",
            'status' => UnitStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => UnitStatus::Inactive]);
    }
}
