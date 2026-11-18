<?php

namespace Database\Factories;

use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opened_by' => User::factory(),
            'training_date' => now()->toDateString(),
            'title' => null,
            'notes' => null,
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }
}
