<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerAddress>
 */
class CustomerAddressFactory extends Factory
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
            'type' => fake()->randomElement(['billing', 'shipping', 'home', 'office']),
            'region' => fake()->randomElement([
                'Addis Ababa', 'Afar', 'Amhara', 'Benishangul-Gumuz', 'Dire Dawa',
                'Gambella', 'Harari', 'Oromia', 'Somali', 'Southern Nations, Nationalities, and Peoples\' Region',
                'Tigray'
            ]),
            'zone' => fake()->optional()->word(),
            'woreda' => fake()->optional()->word(),
            'kebele' => fake()->optional()->word(),
            'city' => fake()->optional()->city(),
            'street_address' => fake()->optional()->streetAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'is_primary' => fake()->boolean(30), // 30% chance of being primary
        ];
    }

    /**
     * Indicate that the address is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Indicate that the address is for billing.
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'billing',
        ]);
    }

    /**
     * Indicate that the address is for shipping.
     */
    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'shipping',
        ]);
    }
}