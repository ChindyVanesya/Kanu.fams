<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\PrivateTrainingParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateTrainingParticipant>
 */
class PrivateTrainingParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'private_training_session_id' => PrivateTrainingSession::factory(),
            'member_id' => Member::factory(),
            'attended_at' => null,
        ];
    }
}
