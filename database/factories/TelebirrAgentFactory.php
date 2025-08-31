<?php

namespace Database\Factories;

use App\Models\TelebirrAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

class TelebirrAgentFactory extends Factory
{
    protected $model = TelebirrAgent::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'short_code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'phone' => $this->faker->phoneNumber(),
            'location' => $this->faker->city(),
            'status' => $this->faker->randomElement(['Active', 'Inactive']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Inactive',
        ]);
    }

    public function dormant(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Dormant',
        ]);
    }
}