<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Receipt;
use App\Models\Branch;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Receipt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'branch_id' => Branch::factory(),
            'number' => $this->faker->unique()->numerify('############'),
            'status' => 'DRAFT',
            'customer_id' => $this->faker->uuid(),
            'currency' => 'ETB',
            'subtotal' => $this->faker->randomFloat(2, 100, 10000),
            'tax_total' => $this->faker->randomFloat(2, 0, 1000),
            'discount_total' => $this->faker->randomFloat(2, 0, 500),
            'grand_total' => function (array $attributes) {
                return $attributes['subtotal'] + $attributes['tax_total'] - $attributes['discount_total'];
            },
            'paid_total' => function (array $attributes) {
                return $attributes['grand_total'];
            },
            'payment_method' => $this->faker->randomElement(['CASH', 'CARD', 'MOBILE', 'TRANSFER', 'MIXED']),
            'created_by' => $this->faker->uuid(),
        ];
    }

    /**
     * Indicate that the receipt is posted.
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'POSTED',
            'posted_at' => now(),
            'posted_by' => $this->faker->uuid(),
        ]);
    }

    /**
     * Indicate that the receipt is voided.
     */
    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'VOIDED',
            'voided_at' => now(),
            'voided_by' => $this->faker->uuid(),
        ]);
    }
}