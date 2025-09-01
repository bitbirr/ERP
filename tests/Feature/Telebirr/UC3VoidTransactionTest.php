<?php

namespace Tests\Feature\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class UC3VoidTransactionTest extends BaseTestCase
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
            'capability_key' => 'telebirr.void',
            'granted' => true,
        ]);

        // Create test agent
        $this->agent = TelebirrAgent::factory()->create([
            'status' => 'Active',
            'short_code' => 'UC3_AGENT'
        ]);

        // Create test bank account
        $this->bankAccount = BankAccount::factory()->create([
            'external_number' => 'UC3_BANK',
            'is_active' => true
        ]);
    }

    /** @test */
    public function uc3_void_wrong_transaction_and_reissue_correct()
    {
        // Step 1: Post WRONG transaction (Distributor) - incorrect amount
        $wrongLoanData = [
            'amount' => 1500.00, // Wrong amount (should be 1000.00)
            'currency' => 'ETB',
            'idempotency_key' => 'uc3-wrong-loan-' . now()->timestamp,
            'external_ref' => 'UC3-WRONG-LOAN-REF',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'UC3 Wrong Loan Amount'
        ];

        $wrongLoanResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/loan', $wrongLoanData);

        $wrongLoanResponse->assertStatus(201)
            ->assertJson([
                'message' => 'Loan transaction posted successfully'
            ]);

        // Get the wrong transaction
        $wrongTransaction = \App\Models\TelebirrTransaction::where('idempotency_key', $wrongLoanData['idempotency_key'])->first();
        $this->assertNotNull($wrongTransaction);
        $this->assertEquals('Posted', $wrongTransaction->status);

        // Step 2: Identify the mistaken transaction via Get All Transactions
        $transactionsResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/telebirr/transactions?agent_id=' . $this->agent->id);

        $transactionsResponse->assertStatus(200);
        $transactions = $transactionsResponse->json('data');
        $this->assertCount(1, $transactions);

        // Step 3: Void the wrong transaction
        $voidResponse = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/telebirr/transactions/' . $wrongTransaction->id . '/void');

        $voidResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Transaction voided successfully'
            ]);

        // Verify transaction is voided
        $wrongTransaction->refresh();
        $this->assertEquals('Voided', $wrongTransaction->status);

        // Verify GL journal is reversed
        $this->assertNotNull($wrongTransaction->gl_journal_id);
        $glJournal = $wrongTransaction->glJournal;
        $this->assertEquals('REVERSED', $glJournal->status);

        // Step 4: Negative guardrail - try to void already voided transaction
        $voidAgainResponse = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/telebirr/transactions/' . $wrongTransaction->id . '/void');

        $voidAgainResponse->assertStatus(400)
            ->assertJson([
                'message' => 'Transaction cannot be voided'
            ]);

        // Step 5: Re-post the CORRECT transaction with new idempotency_key
        $correctLoanData = [
            'amount' => 1000.00, // Correct amount
            'currency' => 'ETB',
            'idempotency_key' => 'uc3-correct-loan-' . now()->timestamp,
            'external_ref' => 'UC3-CORRECT-LOAN-REF',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'UC3 Correct Loan Amount'
        ];

        $correctLoanResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/loan', $correctLoanData);

        $correctLoanResponse->assertStatus(201)
            ->assertJson([
                'message' => 'Loan transaction posted successfully'
            ]);

        // Get the correct transaction
        $correctTransaction = \App\Models\TelebirrTransaction::where('idempotency_key', $correctLoanData['idempotency_key'])->first();
        $this->assertNotNull($correctTransaction);
        $this->assertEquals('Posted', $correctTransaction->status);

        // Step 6: Verify in agent balances that net totals reflect the corrected action
        $balanceResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $balanceResponse->assertStatus(200);

        $balances = $balanceResponse->json('data');
        $agentBalance = collect($balances)->firstWhere('agent.short_code', $this->agent->short_code);

        $this->assertNotNull($agentBalance);
        $this->assertEquals(1000.00, $agentBalance['outstanding_balance']); // Only correct transaction counted

        // Step 7: Verify in transaction summary that voided is excluded and correct is included
        $summaryResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/telebirr/reports/transaction-summary?date_from=' . now()->subDay()->toDateString() . '&date_to=' . now()->addDay()->toDateString());

        $summaryResponse->assertStatus(200);

        $summary = $summaryResponse->json();
        $this->assertEquals(1, $summary['totals']['count']); // Only correct transaction
        $this->assertEquals(1000.00, $summary['totals']['amount']); // Only correct amount

        // Verify by type breakdown
        $byType = collect($summary['by_type']);
        $loanType = $byType->firstWhere('tx_type', 'LOAN');

        $this->assertNotNull($loanType);
        $this->assertEquals(1, $loanType['count']);
        $this->assertEquals(1000.00, $loanType['amount']); // Correct amount

        // Verify by agent breakdown
        $byAgent = collect($summary['by_agent']);
        $agentSummary = $byAgent->firstWhere('agent.short_code', $this->agent->short_code);

        $this->assertNotNull($agentSummary);
        $this->assertEquals(1, $agentSummary['count']); // Only correct transaction
        $this->assertEquals(1000.00, $agentSummary['amount']); // Correct amount

        // Step 8: Verify audit trail integrity - both transactions should exist in database
        $allTransactions = \App\Models\TelebirrTransaction::where('agent_id', $this->agent->id)->get();
        $this->assertCount(2, $allTransactions); // Both wrong (voided) and correct transactions

        $voidedTransaction = $allTransactions->firstWhere('status', 'Voided');
        $postedTransaction = $allTransactions->firstWhere('status', 'Posted');

        $this->assertNotNull($voidedTransaction);
        $this->assertNotNull($postedTransaction);
        $this->assertEquals(1500.00, $voidedTransaction->amount); // Wrong amount preserved
        $this->assertEquals(1000.00, $postedTransaction->amount); // Correct amount
    }

    /** @test */
    public function uc3_void_nonexistent_transaction_returns_404()
    {
        $nonExistentId = (string) \Illuminate\Support\Str::uuid();

        $voidResponse = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/telebirr/transactions/' . $nonExistentId . '/void');

        $voidResponse->assertStatus(404);
    }

    /** @test */
    public function uc3_void_without_permission_returns_403()
    {
        // Create user without void permission
        $userWithoutPermission = User::factory()->create();
        UserPolicy::create([
            'user_id' => $userWithoutPermission->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.post',
            'granted' => true,
        ]);
        UserPolicy::create([
            'user_id' => $userWithoutPermission->id,
            'branch_id' => null,
            'capability_key' => 'telebirr.view',
            'granted' => true,
        ]);
        // Note: No telebirr.void permission

        // Create a transaction
        $loanData = [
            'amount' => 500.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc3-no-permission-loan-' . now()->timestamp,
            'external_ref' => 'UC3-NO-PERM-REF',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'UC3 No Permission Test'
        ];

        $loanResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/loan', $loanData);

        $loanResponse->assertStatus(201);

        $transaction = \App\Models\TelebirrTransaction::where('idempotency_key', $loanData['idempotency_key'])->first();
        $this->assertNotNull($transaction);

        // Try to void without permission
        $voidResponse = $this->actingAs($userWithoutPermission, 'sanctum')
            ->patchJson('/api/telebirr/transactions/' . $transaction->id . '/void');

        $voidResponse->assertStatus(403);
    }
}