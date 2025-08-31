<?php

namespace Database\Factories;

use App\Models\TelebirrTransaction;
use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\GlJournal;
use Illuminate\Database\Eloquent\Factories\Factory;

class TelebirrTransactionFactory extends Factory
{
    protected $model = TelebirrTransaction::class;

    public function definition(): array
    {
        return [
            'tx_type' => $this->faker->randomElement(['TOPUP', 'ISSUE', 'REPAY', 'LOAN']),
            'agent_id' => TelebirrAgent::factory(),
            'bank_account_id' => $this->faker->boolean(50) ? BankAccount::factory() : null,
            'amount' => $this->faker->randomFloat(2, 10, 10000),
            'currency' => $this->faker->randomElement(['ETB', 'USD', 'EUR']),
            'idempotency_key' => $this->faker->unique()->uuid(),
            'gl_journal_id' => GlJournal::factory(),
            'status' => $this->faker->randomElement(['Posted', 'Voided', 'Draft']),
            'remarks' => $this->faker->optional()->sentence(),
            'external_ref' => $this->faker->optional()->uuid(),
            'created_by' => \App\Models\User::factory(),
            'approved_by' => $this->faker->boolean(30) ? \App\Models\User::factory() : null,
            'posted_at' => $this->faker->optional()->dateTime(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Posted',
            'posted_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Voided',
        ]);
    }

    public function topup(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'TOPUP',
            'bank_account_id' => BankAccount::factory(),
        ]);
    }

    public function issue(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'ISSUE',
        ]);
    }

    public function repay(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'REPAY',
            'bank_account_id' => BankAccount::factory(),
        ]);
    }

    public function loan(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'LOAN',
        ]);
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Posted',
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement(['Voided', 'Draft']),
        ]);
    }
}