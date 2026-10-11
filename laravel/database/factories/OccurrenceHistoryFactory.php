<?php

namespace Database\Factories;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OccurrenceHistory>
 */
class OccurrenceHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'occurrence_id' => Occurrence::factory(),
            'user_id' => User::factory()->resident(),
            'type' => OccurrenceHistoryType::Created,
            'message' => null,
            'previous_status' => null,
            'new_status' => OccurrenceStatus::Open,
        ];
    }

    public function statusChangedTo(OccurrenceStatus $de, OccurrenceStatus $para): static
    {
        return $this->state([
            'type' => OccurrenceHistoryType::StatusChanged,
            'previous_status' => $de,
            'new_status' => $para,
        ]);
    }

    public function comment(string $mensagem): static
    {
        return $this->state([
            'type' => OccurrenceHistoryType::Comment,
            'message' => $mensagem,
        ]);
    }
}
