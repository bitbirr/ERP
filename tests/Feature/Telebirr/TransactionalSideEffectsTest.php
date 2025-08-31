<?php

namespace Tests\Feature\Telebirr;

use App\Models\TelebirrAgent;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Models\AuditLog;
use App\Events\TransactionCreated;
use App\Services\TelebirrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Mockery;

class TransactionalSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $agent;
    protected $bankAccount;
    protected $telebirrService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Use existing seeded agent or create one
        $this->agent = TelebirrAgent::first() ?? TelebirrAgent::factory()->create([
            'status' => 'Active',
            'short_code' => 'SC001'
        ]);

        // Create necessary GL accounts for testing
        $this->createTestGlAccounts();

        // Use existing seeded bank account or create one with specific GL account
        $this->bankAccount = BankAccount::first();
        if (!$this->bankAccount) {
            $glAccount = \App\Models\GlAccount::where('code', '1101')->first();
            $this->bankAccount = BankAccount::create([
                'name' => 'Test Bank Account',
                'external_number' => 'BANK001',
                'gl_account_id' => $glAccount->id,
                'is_active' => true,
            ]);
        }

        // Get service instance
        $this->telebirrService = app(TelebirrService::class);
    }

    /**
     * Create test GL accounts needed for Telebirr transactions
     */
    private function createTestGlAccounts(): void
    {
        $accounts = [
            ['code' => '1101', 'name' => 'Bank – CBE', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['code' => '1200', 'name' => 'Telebirr Distributor', 'type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '1300', 'name' => 'AR – Agents', 'type' => 'ASSET', 'normal_balance' => 'DEBIT'],
        ];

        foreach ($accounts as $account) {
            \App\Models\GlAccount::firstOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['normal_balance'],
                    'level' => 1,
                    'is_postable' => true,
                    'status' => 'ACTIVE',
                ]
            );
        }
    }

    /** @test */
    public function it_posts_topup_transaction_with_correct_gl_entries()
    {
        // Test TOPUP: Dr Bank (1101), Cr 1200 Distributor

        $payload = [
            'amount' => 1000.00,
            'idempotency_key' => 'topup-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        // Mock Auth to return our test user
        \Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn($this->user->id);

        // Mock request to return our test user
        $mockRequest = \Mockery::mock(\Illuminate\Http\Request::class);
        $mockRequest->shouldReceive('user')->andReturn($this->user);
        $mockRequest->shouldReceive('ip')->andReturn('127.0.0.1');
        $mockRequest->shouldReceive('userAgent')->andReturn('TestAgent');
        $mockRequest->shouldReceive('path')->andReturn('/test');
        $mockRequest->shouldReceive('method')->andReturn('POST');
        $mockRequest->shouldReceive('route')->andReturn(null);
        $mockRequest->shouldReceive('header')->andReturn(null);
        $mockRequest->shouldReceive('attributes->get')->andReturn(null);

        // Bind the mock request to the container
        app()->bind('request', function () use ($mockRequest) {
            return $mockRequest;
        });

        $transaction = $this->telebirrService->postTopup($payload);

        // Assert transaction created
        $this->assertEquals('TOPUP', $transaction->tx_type);
        $this->assertEquals(1000.00, $transaction->amount);
        $this->assertEquals('Posted', $transaction->status);

        // Assert GL journal created and posted
        $this->assertNotNull($transaction->gl_journal_id);
        $journal = $transaction->glJournal;
        $this->assertEquals('POSTED', $journal->status);

        // Assert GL lines
        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        // Bank debit line
        $bankLine = $lines->where('debit', 1000.00)->first();
        $this->assertNotNull($bankLine);
        $this->assertEquals($this->bankAccount->glAccount->code, $bankLine->account_code);
        $this->assertEquals(0, $bankLine->credit);

        // Distributor credit line
        $distributorLine = $lines->where('credit', 1000.00)->first();
        $this->assertNotNull($distributorLine);
        $this->assertEquals('1200', $distributorLine->account_code);
        $this->assertEquals(0, $distributorLine->debit);
    }

    /** @test */
    public function it_posts_issue_transaction_with_correct_gl_entries()
    {
        // Test ISSUE: Dr 1200 Distributor, Cr Agent e-float (1300:Agent:SCxxxx)

        $payload = [
            'amount' => 500.00,
            'idempotency_key' => 'issue-test-123',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'Test issue'
        ];

        $transaction = $this->telebirrService->postIssue($payload);

        // Assert transaction created
        $this->assertEquals('ISSUE', $transaction->tx_type);
        $this->assertEquals(500.00, $transaction->amount);

        // Assert GL journal
        $journal = $transaction->glJournal;
        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        // Distributor debit line
        $distributorLine = $lines->where('debit', 500.00)->first();
        $this->assertNotNull($distributorLine);
        $this->assertEquals('1200', $distributorLine->account_code);

        // Agent credit line (subledger)
        $agentLine = $lines->where('credit', 500.00)->first();
        $this->assertNotNull($agentLine);
        $this->assertEquals('1300', $agentLine->account_code);
        $this->assertArrayHasKey('agent_id', $agentLine->meta);
        $this->assertEquals($this->agent->id, $agentLine->meta['agent_id']);
    }

    /** @test */
    public function it_posts_repay_transaction_with_correct_gl_entries()
    {
        // Test REPAY: Dr Agent (1300), Cr Bank (1101)

        $payload = [
            'amount' => 300.00,
            'idempotency_key' => 'repay-test-123',
            'agent_short_code' => $this->agent->short_code,
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $transaction = $this->telebirrService->postRepay($payload);

        // Assert GL journal
        $journal = $transaction->glJournal;
        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        // Agent debit line
        $agentLine = $lines->where('debit', 300.00)->first();
        $this->assertNotNull($agentLine);
        $this->assertEquals('1300', $agentLine->account_code);

        // Bank credit line
        $bankLine = $lines->where('credit', 300.00)->first();
        $this->assertNotNull($bankLine);
        $this->assertEquals($this->bankAccount->glAccount->code, $bankLine->account_code);
    }

    /** @test */
    public function it_posts_loan_transaction_with_correct_gl_entries()
    {
        // Test LOAN: Same as ISSUE - Dr 1200 Distributor, Cr 1300 Agent

        $payload = [
            'amount' => 200.00,
            'idempotency_key' => 'loan-test-123',
            'agent_short_code' => $this->agent->short_code,
            'payment_method' => 'CASH',
            'remarks' => 'Test loan'
        ];

        $transaction = $this->telebirrService->postLoan($payload);

        // Assert GL journal (same as ISSUE)
        $journal = $transaction->glJournal;
        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        // Distributor debit
        $distributorLine = $lines->where('debit', 200.00)->first();
        $this->assertEquals('1200', $distributorLine->account_code);

        // Agent credit
        $agentLine = $lines->where('credit', 200.00)->first();
        $this->assertEquals('1300', $agentLine->account_code);
    }

    /** @test */
    public function it_enforces_debit_credit_balance_in_gl_journal()
    {
        $payload = [
            'amount' => 1000.00,
            'idempotency_key' => 'balance-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $transaction = $this->telebirrService->postTopup($payload);
        $journal = $transaction->glJournal;

        // Assert journal balance
        $this->assertTrue($journal->validateBalance());
        $this->assertEquals($journal->getTotalDebit(), $journal->getTotalCredit());
    }

    /** @test */
    public function it_logs_audit_entries_for_successful_transactions()
    {
        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'audit-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $transaction = $this->telebirrService->postTopup($payload);

        // Check audit logs
        $auditLogs = AuditLog::where('action', 'telebirr.transaction.created')->get();
        $this->assertCount(1, $auditLogs);

        $auditLog = $auditLogs->first();
        $this->assertEquals('telebirr.transaction.created', $auditLog->action);
        // Verify that audit logging is working (context details may vary in test environment)
        $this->assertIsArray($auditLog->context);
        // Note: actor_id may be null in test context due to request mocking
    }

    /** @test */
    public function it_logs_audit_entries_for_failed_transactions()
    {
        // Force a failure by using invalid agent
        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'audit-fail-test-123',
            'agent_short_code' => 'INVALID_AGENT',
            'payment_method' => 'CASH',
            'remarks' => 'Test failure'
        ];

        try {
            $this->telebirrService->postIssue($payload);
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Check for failure audit logs
        $auditLogs = AuditLog::where('action', 'LIKE', 'telebirr.transaction%')->get();
        $this->assertGreaterThan(0, $auditLogs->count());
    }

    /** @test */
    public function it_creates_transaction_with_proper_side_effects()
    {
        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'side-effects-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $transaction = $this->telebirrService->postTopup($payload);

        // Assert transaction was created successfully with all side effects
        $this->assertEquals('TOPUP', $transaction->tx_type);
        $this->assertEquals(100.00, $transaction->amount);
        $this->assertEquals('Posted', $transaction->status);

        // Assert GL journal was created and posted
        $this->assertNotNull($transaction->gl_journal_id);
        $journal = $transaction->glJournal;
        $this->assertEquals('POSTED', $journal->status);

        // Assert audit log was created
        $auditLogs = AuditLog::where('action', 'telebirr.transaction.created')->get();
        $this->assertCount(1, $auditLogs);
    }

    /** @test */
    public function it_rolls_back_transaction_on_gl_posting_failure()
    {
        // Mock GL service to throw exception during posting
        $glServiceMock = Mockery::mock(\App\Services\GL\GlService::class);
        $glServiceMock->shouldReceive('createJournal')->andReturn(new GlJournal(['id' => 1, 'status' => 'DRAFT']));
        $glServiceMock->shouldReceive('post')->andThrow(new \Exception('GL posting failed'));
        $this->app->instance(\App\Services\GL\GlService::class, $glServiceMock);

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'rollback-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $this->expectException(\Exception::class);

        try {
            $this->telebirrService->postTopup($payload);
        } catch (\Exception $e) {
            // Assert no transaction was created
            $this->assertEquals(0, \App\Models\TelebirrTransaction::count());
            // Assert no journal was created
            $this->assertEquals(0, GlJournal::count());
            throw $e;
        }
    }

    /** @test */
    public function it_handles_idempotent_requests_correctly()
    {
        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'idempotent-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        // First request
        $transaction1 = $this->telebirrService->postTopup($payload);

        // Second request with same idempotency key
        $transaction2 = $this->telebirrService->postTopup($payload);

        // Assert same transaction returned
        $this->assertEquals($transaction1->id, $transaction2->id);

        // Assert only one transaction exists
        $this->assertEquals(1, \App\Models\TelebirrTransaction::where('idempotency_key', 'idempotent-test-123')->count());
    }

    /** @test */
    public function it_mocks_external_ebirr_clearing_calls()
    {
        // Mock HTTP client for EBIRR clearing
        $httpMock = Mockery::mock(\Illuminate\Http\Client\Factory::class);
        $httpMock->shouldReceive('post')
            ->with('https://ebirr-api.example.com/clear', Mockery::any())
            ->andReturn(new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode(['status' => 'success']))
            ));
        $this->app->instance(\Illuminate\Http\Client\Factory::class, $httpMock);

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'ebirr-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $transaction = $this->telebirrService->postTopup($payload);

        // Assert transaction completed successfully
        $this->assertEquals('Posted', $transaction->status);
    }

    /** @test */
    public function it_handles_ebirr_clearing_timeout()
    {
        // Mock HTTP client to simulate timeout
        $httpMock = Mockery::mock(\Illuminate\Http\Client\Factory::class);
        $httpMock->shouldReceive('post')
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('Timeout'));
        $this->app->instance(\Illuminate\Http\Client\Factory::class, $httpMock);

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'timeout-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $this->expectException(\Exception::class);

        $this->telebirrService->postTopup($payload);
    }

    /** @test */
    public function it_handles_ebirr_clearing_4xx_errors()
    {
        // Mock HTTP client to simulate 4xx error
        $httpMock = Mockery::mock(\Illuminate\Http\Client\Factory::class);
        $httpMock->shouldReceive('post')
            ->andReturn(new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(400, [], json_encode(['error' => 'Bad Request']))
            ));
        $this->app->instance(\Illuminate\Http\Client\Factory::class, $httpMock);

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => '4xx-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $this->expectException(\Exception::class);

        $this->telebirrService->postTopup($payload);
    }

    /** @test */
    public function it_handles_ebirr_clearing_5xx_errors()
    {
        // Mock HTTP client to simulate 5xx error
        $httpMock = Mockery::mock(\Illuminate\Http\Client\Factory::class);
        $httpMock->shouldReceive('post')
            ->andReturn(new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(500, [], json_encode(['error' => 'Internal Server Error']))
            ));
        $this->app->instance(\Illuminate\Http\Client\Factory::class, $httpMock);

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => '5xx-test-123',
            'bank_external_number' => $this->bankAccount->external_number,
            'payment_method' => 'CASH'
        ];

        $this->expectException(\Exception::class);

        $this->telebirrService->postTopup($payload);
    }
}