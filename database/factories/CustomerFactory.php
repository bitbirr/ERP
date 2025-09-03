<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => fake()->randomElement(['individual', 'organization']),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'tax_id' => fake()->optional()->numerify('TAX########'),
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90), // 90% chance of being active
            'metadata' => fake()->optional()->passthrough(['key' => 'value']),
        ];
    }

    /**
     * Indicate that the customer is an individual.
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'individual',
        ]);
    }

    /**
     * Indicate that the customer is an organization.
     */
    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'organization',
        ]);
    }

    /**
     * Indicate that the customer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}