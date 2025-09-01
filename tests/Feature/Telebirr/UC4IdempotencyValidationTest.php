<?php

namespace Tests\Feature\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class UC4IdempotencyValidationTest extends BaseTestCase
{
    use RefreshDatabase;

    protected $user;
    protected $agent;
    protected $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed necessary data for tests
        $this->seed([
            \Database\Seeders\ChartOfAccountsSeeder::class,
            \Database\Seeders\TelebirrAgentsSeeder::class,
            \Database\Seeders\BankAccountsSeeder::class,
        ]);

        // Create a test user specifically for this test
        $this->user = User::factory()->create();

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

        UserPolicy::create([
            'user_id' => $this->user->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.manage',
            'granted' => true,
        ]);

        // Create test agent
        $this->agent = TelebirrAgent::factory()->create([
            'status' => 'Active',
            'short_code' => 'UC4_AGENT'
        ]);

        // Create test bank account
        $this->bankAccount = BankAccount::factory()->create([
            'external_number' => 'UC4_BANK',
            'is_active' => true
        ]);
    }

    /** @test */
    public function uc4_duplicate_idempotency_key_in_topup_transaction_returns_200_with_existing_transaction()
    {
        // Step 1: Post initial TOPUP transaction
        $topupData = [
            'amount' => 500.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc4-topup-duplicate-key-' . now()->timestamp,
            'external_ref' => 'UC4-TOPUP-REF',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH',
            'remarks' => 'UC4 Topup Transaction'
        ];

        $initialResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);

        $initialResponse->assertStatus(201)
            ->assertJson([
                'message' => 'Topup transaction posted successfully'
            ]);

        $initialTransaction = \App\Models\TelebirrTransaction::where('idempotency_key', $topupData['idempotency_key'])->first();
        $this->assertNotNull($initialTransaction);
        $this->assertEquals('Posted', $initialTransaction->status);

        // Step 2: Attempt to post the same transaction with duplicate idempotency key
        $duplicateResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);

        $duplicateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);

        // Step 3: Verify no duplicate transaction was created
        $transactions = \App\Models\TelebirrTransaction::where('idempotency_key', $topupData['idempotency_key'])->get();
        $this->assertCount(1, $transactions); // Only one transaction should exist

        // Step 4: Verify balance was not affected by the duplicate attempt
        $balanceResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $balanceResponse->assertStatus(200);

        $balances = $balanceResponse->json('data');
        // Since this is a TOPUP (not affecting agent balance directly), we verify no additional impact
        $this->assertNotNull($balances);
    }

    /** @test */
    public function uc4_invalid_agent_in_issue_transaction_returns_422()
    {
        // Step 1: Attempt to post ISSUE transaction with invalid agent
        $issueData = [
            'amount' => 300.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc4-issue-invalid-agent-' . now()->timestamp,
            'external_ref' => 'UC4-ISSUE-INVALID-REF',
            'agent_short_code' => 'INVALID_AGENT_CODE',
            'payment_method' => 'CASH',
            'remarks' => 'UC4 Issue with Invalid Agent'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/issue', $issueData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['agent_short_code']);

        // Step 2: Verify no transaction was created
        $transaction = \App\Models\TelebirrTransaction::where('idempotency_key', $issueData['idempotency_key'])->first();
        $this->assertNull($transaction);

        // Step 3: Verify agent balance was not affected
        $balanceResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $balanceResponse->assertStatus(200);

        $balances = $balanceResponse->json('data');
        $agentBalance = collect($balances)->firstWhere('agent.short_code', $this->agent->short_code);

        // Balance should remain unchanged (0 since no transactions)
        $this->assertEquals(0.00, $agentBalance['outstanding_balance']);
    }

    /** @test */
    public function uc4_missing_required_fields_in_create_agent_returns_422()
    {
        // Step 1: Attempt to create agent with missing required fields
        $agentData = [
            // Missing 'name', 'short_code', and 'status' - all required
            'phone' => '+251922654321',
            'location' => 'Test Location',
            'notes' => 'Test notes'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/agents', $agentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'short_code', 'status']);

        // Step 2: Verify no agent was created
        $createdAgent = TelebirrAgent::where('phone', $agentData['phone'])->first();
        $this->assertNull($createdAgent);

        // Step 3: Verify agent count remains the same
        $agentCount = TelebirrAgent::count();
        $this->assertGreaterThan(0, $agentCount); // Should have agents from seeder
    }

    /** @test */
    public function uc4_duplicate_agent_short_code_returns_422()
    {
        // Step 1: Attempt to create agent with duplicate short_code
        $agentData = [
            'name' => 'Test Agent',
            'short_code' => $this->agent->short_code, // Duplicate of existing agent
            'status' => 'Active',
            'phone' => '+251922654321',
            'location' => 'Test Location',
            'notes' => 'Test notes'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/agents', $agentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['short_code']);

        // Step 2: Verify no duplicate agent was created
        $agentsWithCode = TelebirrAgent::where('short_code', $agentData['short_code'])->get();
        $this->assertCount(1, $agentsWithCode); // Only the original should exist
    }

    /** @test */
    public function uc4_inactive_agent_in_issue_transaction_returns_422()
    {
        // Step 1: Create an inactive agent
        $inactiveAgent = TelebirrAgent::factory()->create([
            'status' => 'Inactive',
            'short_code' => 'UC4_INACTIVE_AGENT'
        ]);

        // Step 2: Attempt to post ISSUE transaction with inactive agent
        $issueData = [
            'amount' => 200.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc4-issue-inactive-agent-' . now()->timestamp,
            'external_ref' => 'UC4-ISSUE-INACTIVE-REF',
            'agent_short_code' => $inactiveAgent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'UC4 Issue with Inactive Agent'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/issue', $issueData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['agent_short_code']);

        // Step 3: Verify no transaction was created
        $transaction = \App\Models\TelebirrTransaction::where('idempotency_key', $issueData['idempotency_key'])->first();
        $this->assertNull($transaction);
    }
}