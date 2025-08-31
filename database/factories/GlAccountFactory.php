<?php

namespace Database\Factories;

use App\Models\GlAccount;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class GlAccountFactory extends Factory
{
    protected $model = GlAccount::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE']);
        $normalBalance = match ($type) {
            'ASSET', 'EXPENSE' => 'DEBIT',
            'LIABILITY', 'EQUITY', 'REVENUE' => 'CREDIT',
        };

        return [
            'code' => $this->faker->unique()->regexify('[0-9]{4}'),
            'name' => $this->faker->words(2, true),
            'type' => $type,
            'normal_balance' => $normalBalance,
            'parent_id' => null, // Top level by default
            'level' => 1,
            'is_postable' => $this->faker->boolean(70), // 70% chance of being postable
            'status' => $this->faker->randomElement(['ACTIVE', 'INACTIVE']),
            'branch_id' => null, // Simplified for testing
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ACTIVE',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'INACTIVE',
        ]);
    }

    public function postable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_postable' => true,
        ]);
    }

    public function nonPostable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_postable' => false,
        ]);
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'LIABILITY',
            'normal_balance' => 'CREDIT',
        ]);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'EQUITY',
            'normal_balance' => 'CREDIT',
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'REVENUE',
            'normal_balance' => 'CREDIT',
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'EXPENSE',
            'normal_balance' => 'DEBIT',
        ]);
    }
}