<?php

namespace Database\Factories;

use App\Models\CustomerInteraction;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerInteraction>
 */
class CustomerInteractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'type' => fake()->randomElement(['call', 'email', 'meeting', 'note', 'support_ticket']),
            'direction' => fake()->optional()->randomElement(['inbound', 'outbound']),
            'description' => fake()->paragraph(),
            'metadata' => fake()->optional()->passthrough(['interaction_data' => 'value']),
            'occurred_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the interaction is a call.
     */
    public function call(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'call',
            'duration_minutes' => fake()->numberBetween(5, 60),
        ]);
    }

    /**
     * Indicate that the interaction is a meeting.
     */
    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'meeting',
            'duration_minutes' => fake()->numberBetween(30, 240),
        ]);
    }
}