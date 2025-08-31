<?php

namespace Database\Factories;

use App\Models\GlJournal;
use Illuminate\Database\Eloquent\Factories\Factory;

class GlJournalFactory extends Factory
{
    protected $model = GlJournal::class;

    public function definition(): array
    {
        return [
            'source' => $this->faker->randomElement(['TELEBIRR', 'MANUAL', 'POS']),
            'source_id' => $this->faker->optional()->randomNumber(),
            'branch_id' => null,
            'date' => $this->faker->date(),
            'memo' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['DRAFT', 'POSTED', 'VOIDED']),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'POSTED',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'DRAFT',
        ]);
    }
}