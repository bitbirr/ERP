<?php

namespace Database\Factories;

use App\Models\CustomerContact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerContact>
 */
class CustomerContactFactory extends Factory
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
            'type' => fake()->randomElement(['phone', 'email', 'website']),
            'value' => fake()->randomElement([
                fake()->phoneNumber(),
                fake()->email(),
                fake()->url(),
            ]),
            'is_primary' => fake()->boolean(30), // 30% chance of being primary
        ];
    }

    /**
     * Indicate that the contact is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Indicate that the contact is a phone number.
     */
    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'phone',
            'value' => fake()->phoneNumber(),
        ]);
    }

    /**
     * Indicate that the contact is an email.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
            'value' => fake()->email(),
        ]);
    }
}