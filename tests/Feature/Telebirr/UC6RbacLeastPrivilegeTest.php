<?php

namespace Tests\Feature\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class UC6RbacLeastPrivilegeTest extends BaseTestCase
{
    use RefreshDatabase;

    protected $managerUser;
    protected $distributorUser;
    protected $financeUser;
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

        // Create test agent
        $this->agent = TelebirrAgent::factory()->create([
            'status' => 'Active',
            'short_code' => 'UC6_AGENT'
        ]);

        // Create test GL account first
        $glAccount = \App\Models\GlAccount::factory()->create();

        // Create test bank account
        $this->bankAccount = BankAccount::create([
            'name' => 'Test Bank Account',
            'external_number' => '1234567890',
            'account_number' => '1234567890',
            'is_active' => true,
            'gl_account_id' => $glAccount->id,
        ]);

        // Create Manager user (read-only)
        $this->managerUser = User::factory()->create(['email' => 'manager@test.com']);
        UserPolicy::create([
            'user_id' => $this->managerUser->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.view',
            'granted' => true,
        ]);

        // Create Distributor user (post/void)
        $this->distributorUser = User::factory()->create(['email' => 'distributor@test.com']);
        UserPolicy::create([
            'user_id' => $this->distributorUser->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.view',
            'granted' => true,
        ]);
        UserPolicy::create([
            'user_id' => $this->distributorUser->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.post',
            'granted' => true,
        ]);
        UserPolicy::create([
            'user_id' => $this->distributorUser->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.void',
            'granted' => true,
        ]);

        // Create Finance user (post/void + GL-adjacent)
        $this->financeUser = User::factory()->create(['email' => 'finance@test.com']);
        UserPolicy::create([
            'user_id' => $this->financeUser->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.view',
            'granted' => true,
        ]);
        UserPolicy::create([
            'user_id' => $this->financeUser->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.post',
            'granted' => true,
        ]);
        UserPolicy::create([
            'user_id' => $this->financeUser->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.void',
            'granted' => true,
        ]);
        UserPolicy::create([
            'user_id' => $this->financeUser->id,
            'branch_id' => null,
            'capability_key' => 'gl.view',
            'granted' => true,
        ]);
    }

    /** @test */
    public function manager_cannot_mutate_but_can_read()
    {
        // Manager can read agents
        $agentsResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/telebirr/agents');
        $agentsResponse->assertStatus(200);

        // Manager can read specific agent
        $agentResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/telebirr/agents/' . $this->agent->id);
        $agentResponse->assertStatus(200);

        // Manager can read transactions
        $transactionsResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/telebirr/transactions');
        $transactionsResponse->assertStatus(200);

        // Manager can read reports
        $balancesResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');
        $balancesResponse->assertStatus(200);

        // Manager CANNOT create agent (403)
        $createAgentData = [
            'name' => 'Test Agent',
            'short_code' => 'TEST_AGENT',
            'phone' => '+251911000000',
            'status' => 'Active'
        ];
        $createResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/telebirr/agents', $createAgentData);
        $createResponse->assertStatus(403);

        // Manager CANNOT post transaction (403)
        $topupData = [
            'amount' => 100.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc6-manager-topup-' . now()->timestamp . '-' . rand(1000, 9999),
            'external_ref' => 'UC6-MANAGER-TOPUP',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'BANK_TRANSFER',
            'remarks' => 'UC6 Manager Topup Test'
        ];
        $topupResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);
        $topupResponse->assertStatus(403);
    }

    /** @test */
    public function distributor_can_post_and_void_telebirr_transactions()
    {
        // First, post a transaction as distributor
        $topupData = [
            'amount' => 200.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc6-distributor-topup-' . now()->timestamp . '-' . rand(1000, 9999),
            'external_ref' => 'UC6-DISTRIBUTOR-TOPUP',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'BANK_TRANSFER',
            'remarks' => 'UC6 Distributor Topup Test'
        ];

        $topupResponse = $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);
        $topupResponse->assertStatus(201)
            ->assertJson(['message' => 'Topup transaction posted successfully']);

        // Get the transaction
        $transaction = \App\Models\TelebirrTransaction::where('idempotency_key', $topupData['idempotency_key'])->first();
        $this->assertNotNull($transaction);

        // Distributor can void the transaction
        $voidResponse = $this->actingAs($this->distributorUser, 'sanctum')
            ->patchJson('/api/telebirr/transactions/' . $transaction->id . '/void');
        $voidResponse->assertStatus(200)
            ->assertJson(['message' => 'Transaction voided successfully']);

        // Verify transaction is voided
        $transaction->refresh();
        $this->assertEquals('Voided', $transaction->status);
    }

    /** @test */
    public function finance_can_perform_bank_topup_void_and_read_gl()
    {
        // Finance can post topup transaction
        $topupData = [
            'amount' => 300.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc6-finance-topup-' . now()->timestamp . '-' . rand(1000, 9999),
            'external_ref' => 'UC6-FINANCE-TOPUP',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'BANK_TRANSFER',
            'remarks' => 'UC6 Finance Topup Test'
        ];

        $topupResponse = $this->actingAs($this->financeUser, 'sanctum')
            ->postJson('/api/telebirr/transactions/topup', $topupData);
        $topupResponse->assertStatus(201);

        // Get the transaction
        $transaction = \App\Models\TelebirrTransaction::where('idempotency_key', $topupData['idempotency_key'])->first();
        $this->assertNotNull($transaction);

        // Finance can void the transaction
        $voidResponse = $this->actingAs($this->financeUser, 'sanctum')
            ->patchJson('/api/telebirr/transactions/' . $transaction->id . '/void');
        $voidResponse->assertStatus(200);

        // Finance can read GL journals
        $glResponse = $this->actingAs($this->financeUser, 'sanctum')
            ->getJson('/api/gl/journals');
        $glResponse->assertStatus(200);

        // Finance can read GL accounts
        $accountsResponse = $this->actingAs($this->financeUser, 'sanctum')
            ->getJson('/api/gl/accounts');
        $accountsResponse->assertStatus(200);
    }

    /** @test */
    public function distributor_cannot_manage_agents()
    {
        // Distributor CANNOT create agent (403)
        $createAgentData = [
            'name' => 'Distributor Agent',
            'short_code' => 'DIST_AGENT',
            'phone' => '+251922000000',
            'status' => 'Active'
        ];
        $createResponse = $this->actingAs($this->distributorUser, 'sanctum')
            ->postJson('/api/telebirr/agents', $createAgentData);
        $createResponse->assertStatus(403);
    }

    /** @test */
    public function finance_cannot_manage_agents()
    {
        // Finance CANNOT create agent (403)
        $createAgentData = [
            'name' => 'Finance Agent',
            'short_code' => 'FIN_AGENT',
            'phone' => '+251933000000',
            'status' => 'Active'
        ];
        $createResponse = $this->actingAs($this->financeUser, 'sanctum')
            ->postJson('/api/telebirr/agents', $createAgentData);
        $createResponse->assertStatus(403);
    }
}