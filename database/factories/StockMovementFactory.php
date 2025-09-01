<?php

namespace Database\Factories;

use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'branch_id' => Branch::factory(),
            'qty' => $this->faker->numberBetween(1, 100),
            'type' => $this->faker->randomElement(['OPENING', 'RECEIVE', 'ISSUE', 'ADJUST']),
            'ref' => $this->faker->uuid(),
            'meta' => [],
            'created_by' => User::factory(),
        ];
    }

    public function opening(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'OPENING',
        ]);
    }

    public function receive(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'RECEIVE',
        ]);
    }

    public function issue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ISSUE',
        ]);
    }

    public function adjust(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ADJUST',
        ]);
    }

    public function withProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    public function withBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branch->id,
        ]);
    }

    public function withUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}