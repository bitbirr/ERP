<?php

namespace Tests\Feature\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UC2LoanRepaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $agent;
    protected $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the default test user from base TestCase
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

        // Create test agent
        $this->agent = TelebirrAgent::factory()->create([
            'status' => 'Active',
            'short_code' => 'UC2_AGENT'
        ]);

        // Create test bank account
        $this->bankAccount = BankAccount::factory()->create([
            'external_number' => 'UC2_BANK',
            'is_active' => true
        ]);
    }

    /** @test */
    public function uc2_loan_partial_repayment_balance_check()
    {
        // Step 1: Post LOAN transaction (Distributor)
        $loanData = [
            'amount' => 1000.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc2-loan-' . now()->timestamp,
            'external_ref' => 'UC2-LOAN-REF',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'UC2 Test Loan'
        ];

        $loanResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/loan', $loanData);

        $loanResponse->assertStatus(201)
            ->assertJson([
                'message' => 'Loan transaction posted successfully'
            ]);

        // Verify loan transaction created
        $this->assertDatabaseHas('telebirr_transactions', [
            'amount' => 1000.00,
            'tx_type' => 'LOAN',
            'status' => 'Posted',
            'agent_id' => $this->agent->id
        ]);

        // Step 2: Post partial REPAY transaction (Finance)
        $repayData = [
            'amount' => 600.00, // Partial repayment
            'currency' => 'ETB',
            'idempotency_key' => 'uc2-repay-' . now()->timestamp,
            'external_ref' => 'UC2-REPAY-REF',
            'agent_short_code' => $this->agent->short_code,
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'BANK_TRANSFER',
            'remarks' => 'UC2 Test Partial Repayment'
        ];

        $repayResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/repay', $repayData);

        $repayResponse->assertStatus(201)
            ->assertJson([
                'message' => 'Repayment transaction posted successfully'
            ]);

        // Verify repayment transaction created
        $this->assertDatabaseHas('telebirr_transactions', [
            'amount' => 600.00,
            'tx_type' => 'REPAY',
            'status' => 'Posted',
            'agent_id' => $this->agent->id,
            'bank_account_id' => $this->bankAccount->id
        ]);

        // Step 3: Manager verifies agent balance (should be 400.00 outstanding)
        $balanceResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/telebirr/reports/agent-balances');

        $balanceResponse->assertStatus(200);

        $balances = $balanceResponse->json('data');
        $agentBalance = collect($balances)->firstWhere('agent.short_code', $this->agent->short_code);

        $this->assertNotNull($agentBalance);
        $this->assertEquals(400.00, $agentBalance['outstanding_balance']);

        // Step 4: Manager verifies transaction summary
        $summaryResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/telebirr/reports/transaction-summary?date_from=' . now()->subDay()->toDateString() . '&date_to=' . now()->addDay()->toDateString());

        $summaryResponse->assertStatus(200);

        $summary = $summaryResponse->json();
        $this->assertEquals(2, $summary['totals']['count']); // Both loan and repayment
        $this->assertEquals(400.00, $summary['totals']['amount']); // Net amount (1000 - 600)

        // Verify by type breakdown
        $byType = collect($summary['by_type']);
        $loanType = $byType->firstWhere('tx_type', 'LOAN');
        $repayType = $byType->firstWhere('tx_type', 'REPAY');

        $this->assertNotNull($loanType);
        $this->assertEquals(1, $loanType['count']);
        $this->assertEquals(1000.00, $loanType['amount']);

        $this->assertNotNull($repayType);
        $this->assertEquals(1, $repayType['count']);
        $this->assertEquals(600.00, $repayType['amount']);

        // Verify by agent breakdown
        $byAgent = collect($summary['by_agent']);
        $agentSummary = $byAgent->firstWhere('agent.short_code', $this->agent->short_code);

        $this->assertNotNull($agentSummary);
        $this->assertEquals(2, $agentSummary['count']); // Both transactions for this agent
        $this->assertEquals(400.00, $agentSummary['amount']); // Net amount
    }

    /** @test */
    public function uc2_idempotency_protection()
    {
        // Test idempotency for loan transaction
        $loanData = [
            'amount' => 500.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc2-idempotent-loan',
            'external_ref' => 'UC2-IDEMPOTENT-REF',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'UC2 Idempotent Test'
        ];

        // First request
        $firstResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/loan', $loanData);

        $firstResponse->assertStatus(201);

        // Second request with same idempotency key
        $secondResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/loan', $loanData);

        $secondResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Transaction already processed'
            ]);

        // Verify only one transaction was created
        $this->assertEquals(1, \App\Models\TelebirrTransaction::where('idempotency_key', 'uc2-idempotent-loan')->count());
    }

    /** @test */
    public function uc2_gl_journal_verification()
    {
        // Post loan transaction
        $loanData = [
            'amount' => 800.00,
            'currency' => 'ETB',
            'idempotency_key' => 'uc2-gl-loan-' . now()->timestamp,
            'external_ref' => 'UC2-GL-REF',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'UC2 GL Test'
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/telebirr/transactions/loan', $loanData);

        // Verify GL journal was created and posted
        $transaction = \App\Models\TelebirrTransaction::where('idempotency_key', $loanData['idempotency_key'])->first();
        $this->assertNotNull($transaction->gl_journal_id);

        $journal = $transaction->glJournal;
        $this->assertEquals('POSTED', $journal->status);

        // Verify GL lines: Dr 1200 Distributor, Cr 1300 Agent
        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        $distributorLine = $lines->where('debit', 800.00)->first();
        $this->assertNotNull($distributorLine);
        $this->assertEquals('1200', $distributorLine->account_code);

        $agentLine = $lines->where('credit', 800.00)->first();
        $this->assertNotNull($agentLine);
        $this->assertEquals('1300', $agentLine->account_code);

        // Verify journal balance
        $this->assertTrue($journal->validateBalance());
    }
}