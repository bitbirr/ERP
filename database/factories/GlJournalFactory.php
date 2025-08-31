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
            'journal_no' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{6}'),
            'journal_date' => $this->faker->date(),
            'currency' => 'ETB',
            'fx_rate' => 1.0,
            'source' => $this->faker->randomElement(['TELEBIRR', 'MANUAL', 'POS']),
            'reference' => $this->faker->optional()->word(),
            'memo' => $this->faker->sentence(),
            'branch_id' => null,
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