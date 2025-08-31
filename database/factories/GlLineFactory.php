<?php

namespace Database\Factories;

use App\Models\GlLine;
use App\Models\GlJournal;
use Illuminate\Database\Eloquent\Factories\Factory;

class GlLineFactory extends Factory
{
    protected $model = GlLine::class;

    public function definition(): array
    {
        return [
            'journal_id' => GlJournal::factory(),
            'line_no' => $this->faker->numberBetween(1, 100),
            'account_code' => $this->faker->regexify('[0-9]{4}'),
            'debit' => $this->faker->randomFloat(2, 0, 10000),
            'credit' => $this->faker->randomFloat(2, 0, 10000),
            'memo' => $this->faker->optional()->sentence(),
            'meta' => $this->faker->optional()->json(),
        ];
    }
}