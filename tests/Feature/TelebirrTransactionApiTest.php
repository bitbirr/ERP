<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TelebirrAgent;
use App\Models\TelebirrTransaction;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class TelebirrTransactionApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Assign telebirr capabilities to the default test user
        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.post',
            'granted' => true,
        ]);

        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.view',
            'granted' => true,
        ]);
    }

    /** @test */
    public function it_can_post_issue_transaction()
    {
        $agent = TelebirrAgent::factory()->create();

        $transactionData = [
            'amount' => 500.00,
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-REF-001',
            'remarks' => 'Test issue transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $transactionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'tx_type',
                    'amount',
                    'status',
                    'agent' => [
                        'id',
                        'name',
                        'short_code',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('telebirr_transactions', [
            'amount' => 500.00,
            'tx_type' => 'ISSUE',
            'status' => 'Posted',
        ]);
    }

    /** @test */
    public function it_can_post_loan_transaction()
    {
        $agent = TelebirrAgent::factory()->create();

        $transactionData = [
            'amount' => 1000.00,
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-LOAN-001',
            'remarks' => 'Test loan transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/loan', $transactionData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Loan transaction posted successfully',
            ]);

        $this->assertDatabaseHas('telebirr_transactions', [
            'amount' => 1000.00,
            'tx_type' => 'LOAN',
            'status' => 'Posted',
        ]);
    }

    /** @test */
    public function it_can_post_repay_transaction()
    {
        $agent = TelebirrAgent::factory()->create();
        $bankAccount = BankAccount::factory()->create();

        $transactionData = [
            'amount' => 750.00,
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-REPAY-001',
            'remarks' => 'Test repayment transaction',
            'agent_short_code' => $agent->short_code,
            'bank_external_number' => $bankAccount->external_number,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/repay', $transactionData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Repayment transaction posted successfully',
            ]);

        $this->assertDatabaseHas('telebirr_transactions', [
            'amount' => 750.00,
            'tx_type' => 'REPAY',
            'status' => 'Posted',
        ]);
    }

    /** @test */
    public function it_can_post_topup_transaction()
    {
        $bankAccount = BankAccount::factory()->create();

        $transactionData = [
            'amount' => 2000.00,
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-TOPUP-001',
            'remarks' => 'Test topup transaction',
            'bank_external_number' => $bankAccount->external_number,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/topup', $transactionData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Topup transaction posted successfully',
            ]);

        $this->assertDatabaseHas('telebirr_transactions', [
            'amount' => 2000.00,
            'tx_type' => 'TOPUP',
            'status' => 'Posted',
        ]);
    }

    /** @test */
    public function it_validates_issue_transaction_request()
    {
        $invalidData = [
            'amount' => 0, // Below minimum
            'currency' => 'XYZ', // Invalid currency
            'idempotency_key' => '', // Empty
            'agent_short_code' => 'NONEXISTENT', // Non-existent agent
            'remarks' => '', // Required for issue
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount',
                    'currency',
                    'idempotency_key',
                    'agent_short_code',
                    'remarks',
                ],
            ]);
    }

    /** @test */
    public function it_validates_repay_transaction_request()
    {
        $invalidData = [
            'amount' => 1000000000.00, // Above maximum
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->uuid(), // Not unique (reuse same key)
            'agent_short_code' => 'NONEXISTENT',
            'bank_external_number' => 'NONEXISTENT',
        ];

        // First create a transaction with the same idempotency key
        TelebirrTransaction::factory()->create([
            'idempotency_key' => $invalidData['idempotency_key'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/repay', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount',
                    'idempotency_key',
                    'agent_short_code',
                    'bank_external_number',
                ],
            ]);
    }

    /** @test */
    public function it_validates_topup_transaction_request()
    {
        $invalidData = [
            'amount' => -100.00, // Negative amount
            'currency' => 'etb', // Lowercase (should be uppercase)
            'idempotency_key' => str_repeat('a', 256), // Too long
            'bank_external_number' => '', // Empty
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/topup', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    /** @test */
    public function it_rejects_duplicate_idempotency_key()
    {
        $agent = TelebirrAgent::factory()->create();
        $idempotencyKey = $this->faker->unique()->uuid();

        // First transaction
        $firstTransactionData = [
            'amount' => 500.00,
            'currency' => 'ETB',
            'idempotency_key' => $idempotencyKey,
            'external_ref' => 'EXT-REF-001',
            'remarks' => 'First transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $firstTransactionData)
            ->assertStatus(201);

        // Second transaction with same idempotency key
        $secondTransactionData = [
            'amount' => 300.00,
            'currency' => 'ETB',
            'idempotency_key' => $idempotencyKey, // Same key
            'external_ref' => 'EXT-REF-002',
            'remarks' => 'Second transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $secondTransactionData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'idempotency_key',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_amount_with_too_many_decimals()
    {
        $agent = TelebirrAgent::factory()->create();

        $invalidData = [
            'amount' => 500.123456, // More than 2 decimal places
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-REF-001',
            'remarks' => 'Test transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_invalid_phone_format_in_agent_creation()
    {
        $invalidData = [
            'name' => 'Test Agent',
            'short_code' => 'TST001',
            'phone' => 'invalid-phone-format', // Invalid format
            'location' => 'Test Location',
            'status' => 'Active',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/agents', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'phone',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_agent_short_code_too_long()
    {
        $invalidData = [
            'name' => 'Test Agent',
            'short_code' => str_repeat('A', 51), // 51 characters, exceeds max 50
            'phone' => '+251911123456',
            'location' => 'Test Location',
            'status' => 'Active',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/agents', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'short_code',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_transaction_with_invalid_currency()
    {
        $agent = TelebirrAgent::factory()->create();

        $invalidData = [
            'amount' => 500.00,
            'currency' => 'XYZ', // Not in allowed currencies
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-REF-001',
            'remarks' => 'Test transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'currency',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_transaction_with_amount_too_high()
    {
        $agent = TelebirrAgent::factory()->create();

        $invalidData = [
            'amount' => 1000000000.00, // Above maximum allowed
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-REF-001',
            'remarks' => 'Test transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_transaction_with_amount_too_low()
    {
        $agent = TelebirrAgent::factory()->create();

        $invalidData = [
            'amount' => 0.005, // Below minimum allowed
            'currency' => 'ETB',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'external_ref' => 'EXT-REF-001',
            'remarks' => 'Test transaction',
            'agent_short_code' => $agent->short_code,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/telebirr/transactions/issue', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'amount',
                ],
            ]);
    }
}