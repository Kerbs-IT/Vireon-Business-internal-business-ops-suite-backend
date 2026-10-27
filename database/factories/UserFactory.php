<?php

namespace Database\Factories;

use App\Models\Roles;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role_id' => Roles::factory(),
            'position' => 'Software Developer',
            'date_hired' => now(),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    // Handy state methods so tests read cleanly
    public function admin(): static
    {
        return $this->state(fn() => ['role_id' => Roles::firstOrCreate(['name' => 'admin'])->id]);
    }

    public function manager(): static
    {
        return $this->state(fn() => ['role_id' => Roles::firstOrCreate(['name' => 'manager'])->id]);
    }

    public function employee(): static
    {
        return $this->state(fn() => ['role_id' => Roles::firstOrCreate(['name' => 'employee'])->id]);
    }
}
