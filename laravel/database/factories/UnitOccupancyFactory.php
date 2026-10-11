<?php

namespace Database\Factories;

use App\Enums\OccupantType;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOccupancy>
 */
class UnitOccupancyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'user_id' => User::factory()->resident(),
            'occupant_type' => OccupantType::Tenant,
            'started_at' => now()->subMonths(6)->toDateString(),
            'ended_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * Mantém o atalho users.unit_id em acordo com o vínculo vigente.
     *
     * Em produção quem faz isso é a LinkResidentToUnitAction, dentro da mesma
     * transação. Sem repetir aqui, a fábrica produziria um estado que a
     * aplicação nunca cria — morador com vínculo ativo e unit_id vazio — e o
     * teste passaria a medir esse estado impossível em vez da regra.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (UnitOccupancy $occupancy) {
            if ($occupancy->is_active) {
                User::where('id', $occupancy->user_id)->update(['unit_id' => $occupancy->unit_id]);
            }
        });
    }

    public function owner(): static
    {
        return $this->state(['occupant_type' => OccupantType::Owner]);
    }

    /** Vínculo encerrado: a linha continua, marcada com a data de saída. */
    public function ended(): static
    {
        return $this->state([
            'ended_at' => now()->subMonth()->toDateString(),
            'is_active' => false,
        ]);
    }
}
