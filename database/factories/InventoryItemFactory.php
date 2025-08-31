<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        $onHand = $this->faker->numberBetween(0, 1000);
        return [
            'product_id' => Product::factory(),
            'branch_id' => Branch::factory(),
            'on_hand' => $onHand,
            'reserved' => $this->faker->numberBetween(0, $onHand), // Ensure reserved <= on_hand
        ];
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

    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'on_hand' => $this->faker->numberBetween(10, 1000),
            'reserved' => $this->faker->numberBetween(0, 50),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'on_hand' => 0,
            'reserved' => 0,
        ]);
    }
}