<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_code' => null,
            'name' => fake()->name(),
            'birth_date' => null,
            'phone' => null,
            'has_jersey' => false,
            'status' => 'active',
        ];
    }
}
