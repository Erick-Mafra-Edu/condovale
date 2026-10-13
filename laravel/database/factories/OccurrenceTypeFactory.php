<?php

namespace Database\Factories;

use App\Models\OccurrenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OccurrenceType>
 */
class OccurrenceTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // O nome é único na tabela: sem o unique() da fábrica, dois tipos
            // sorteados iguais fariam o teste falhar por colisão em vez de
            // pela regra que ele mede.
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }

    /** Tipo aposentado: continua nos chamados antigos, mas não entra em chamado novo. */
    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
