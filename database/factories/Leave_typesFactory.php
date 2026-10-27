<?php

namespace Database\Factories;

use App\Models\Leave_types;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave_types>
 */
class Leave_typesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Vacation', 'Sick', 'Emergency']),
            'default_days' => $this->faker->numberBetween(5, 15),
        ];
    }
}
