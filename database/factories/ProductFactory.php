<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        $types = ['YIMULU', 'VOUCHER', 'EVD', 'SIM', 'TELEBIRR', 'E_AIRTIME'];
        $strategies = ['FIXED', 'DISCOUNT', 'EXACT', 'MARKUP'];
        return [
            'code' => strtoupper(Str::random(8)),
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement($types),
            'uom' => 'PCS',
            'price' => $this->faker->randomFloat(3, 10, 1000),
            'cost' => $this->faker->randomFloat(3, 5, 900),
            'discount_percent' => $this->faker->optional()->randomFloat(2, 0, 20),
            'pricing_strategy' => $this->faker->optional()->randomElement($strategies),
            'is_active' => $this->faker->boolean(90),
            'meta' => [],
        ];
    }
}