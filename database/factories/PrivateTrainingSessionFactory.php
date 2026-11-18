<?php

namespace Database\Factories;

use App\Models\PrivateTrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateTrainingSession>
 */
class PrivateTrainingSessionFactory extends Factory
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
            'training_date' => now('Asia/Jayapura')->toDateString(),
            'title' => fake()->sentence(3),
            'notes' => null,
            'price_per_member' => 125000,
            'status' => 'open',
            'opened_at' => now('Asia/Jayapura'),
            'closed_at' => null,
        ];
    }
}
