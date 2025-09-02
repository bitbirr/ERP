<?php

namespace Database\Factories;

use App\Models\CustomerNote;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerNote>
 */
class CustomerNoteFactory extends Factory
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
            'content' => fake()->paragraph(),
            'type' => fake()->randomElement(['general', 'complaint', 'feedback', 'internal']),
            'is_pinned' => fake()->boolean(20), // 20% chance of being pinned
        ];
    }

    /**
     * Indicate that the note is internal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }

    /**
     * Indicate that the note is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => false,
        ]);
    }
}