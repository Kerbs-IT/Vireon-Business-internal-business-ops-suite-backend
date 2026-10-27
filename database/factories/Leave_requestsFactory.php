<?php

namespace Database\Factories;

use App\Models\Leave_requests;
use App\Models\Leave_types;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave_requests>
 */
class Leave_requestsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'leave_type_id' => Leave_types::factory(), // adjust once leave_types is seeded/factoried
            'start_date' => now(),
            'end_date' => now()->addDays(2),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }
}
