<?php

namespace Database\Factories;

use App\Models\PrivateTrainingParticipant;
use App\Models\PrivateTrainingPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateTrainingPayment>
 */
class PrivateTrainingPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'private_training_participant_id' => PrivateTrainingParticipant::factory(),
            'amount' => fake()->numberBetween(10000, 50000),
            'paid_at' => now('Asia/Jayapura'),
            'recorded_by' => User::factory(),
        ];
    }
}
