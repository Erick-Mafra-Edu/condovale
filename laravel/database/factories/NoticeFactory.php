<?php

namespace Database\Factories;

use App\Enums\NoticeStatus;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'content' => fake()->paragraph(),
            // Publicar comunicado é caso de uso do síndico; um autor morador
            // produziria um registro que a aplicação recusaria criar.
            'author_id' => User::factory()->syndic(),
            'status' => NoticeStatus::Published,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'status' => NoticeStatus::Draft,
            'published_at' => null,
        ]);
    }
}
