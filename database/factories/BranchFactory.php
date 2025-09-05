<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company . ' Branch',
            'code' => strtoupper(Str::random(5)),
            'address' => $this->faker->address,
            'phone' => $this->faker->phoneNumber,
            'manager' => $this->faker->name,
        ];
    }
}