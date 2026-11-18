<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'training_session_id' => TrainingSession::factory(),
            'attended_at' => now(),
        ];
    }
}
