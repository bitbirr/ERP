<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\GlAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Bank Account',
            'external_number' => $this->faker->unique()->regexify('[0-9]{10}'),
            'account_number' => $this->faker->bankAccountNumber(),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'gl_account_id' => GlAccount::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}