<?php

namespace Tests\Unit;

use App\Models\TelebirrAgent;
use App\Models\TelebirrTransaction;
use App\Models\BankAccount;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Services\TelebirrService;
use App\Services\GL\GlService;
use App\Domain\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Exception;

class TelebirrServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TelebirrService $service;
    protected GlService $glService;
    protected AuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock services
        $this->glService = $this->createMock(GlService::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);

        $this->service = new TelebirrService($this->glService, $this->auditLogger);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_post_topup_transaction()
    {
        $agent = TelebirrAgent::factory()->active()->create();
        $bankAccount = BankAccount::factory()->active()->create();

        $payload = [
            'amount' => 1000.00,
            'currency' => 'ETB',
            'idempotency_key' => 'test-key-123',
            'bank_external_number' => $bankAccount->external_number,
            'external_ref' => 'REF123',
            'remarks' => 'Test topup',
        ];

        // Mock GL service methods
        $mockJournal = new GlJournal(['id' => 1, 'status' => 'POSTED']);
        $this->glService->expects($this->once())
            ->method('createJournal')
            ->willReturn($mockJournal);
        $this->glService->expects($this->once())
            ->method('post')
            ->with($mockJournal);

        $transaction = $this->service->postTopup($payload);

        $this->assertInstanceOf(TelebirrTransaction::class, $transaction);
        $this->assertEquals('TOPUP', $transaction->tx_type);
        $this->assertEquals(1000.00, $transaction->amount);
        $this->assertEquals($bankAccount->id, $transaction->bank_account_id);
        $this->assertEquals('Posted', $transaction->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_post_issue_transaction()
    {
        $agent = TelebirrAgent::factory()->active()->create();

        $payload = [
            'amount' => 500.00,
            'currency' => 'ETB',
            'idempotency_key' => 'test-issue-key',
            'agent_short_code' => $agent->short_code,
            'external_ref' => 'ISSUE123',
            'remarks' => 'Test issue',
        ];

        // Mock GL service methods
        $mockJournal = new GlJournal(['id' => 2, 'status' => 'POSTED']);
        $this->glService->expects($this->once())
            ->method('createJournal')
            ->willReturn($mockJournal);
        $this->glService->expects($this->once())
            ->method('post')
            ->with($mockJournal);

        $transaction = $this->service->postIssue($payload);

        $this->assertInstanceOf(TelebirrTransaction::class, $transaction);
        $this->assertEquals('ISSUE', $transaction->tx_type);
        $this->assertEquals(500.00, $transaction->amount);
        $this->assertEquals($agent->id, $transaction->agent_id);
        $this->assertEquals('Posted', $transaction->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_post_repay_transaction()
    {
        $agent = TelebirrAgent::factory()->active()->create();
        $bankAccount = BankAccount::factory()->active()->create();

        $payload = [
            'amount' => 300.00,
            'currency' => 'ETB',
            'idempotency_key' => 'test-repay-key',
            'agent_short_code' => $agent->short_code,
            'bank_external_number' => $bankAccount->external_number,
            'external_ref' => 'REPAY123',
            'remarks' => 'Test repayment',
        ];

        // Mock GL service methods
        $mockJournal = new GlJournal(['id' => 3, 'status' => 'POSTED']);
        $this->glService->expects($this->once())
            ->method('createJournal')
            ->willReturn($mockJournal);
        $this->glService->expects($this->once())
            ->method('post')
            ->with($mockJournal);

        $transaction = $this->service->postRepay($payload);

        $this->assertInstanceOf(TelebirrTransaction::class, $transaction);
        $this->assertEquals('REPAY', $transaction->tx_type);
        $this->assertEquals(300.00, $transaction->amount);
        $this->assertEquals($agent->id, $transaction->agent_id);
        $this->assertEquals($bankAccount->id, $transaction->bank_account_id);
        $this->assertEquals('Posted', $transaction->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_post_loan_transaction()
    {
        $agent = TelebirrAgent::factory()->active()->create();

        $payload = [
            'amount' => 200.00,
            'currency' => 'ETB',
            'idempotency_key' => 'test-loan-key',
            'agent_short_code' => $agent->short_code,
            'external_ref' => 'LOAN123',
            'remarks' => 'Test loan',
        ];

        // Mock GL service methods
        $mockJournal = new GlJournal(['id' => 4, 'status' => 'POSTED']);
        $this->glService->expects($this->once())
            ->method('createJournal')
            ->willReturn($mockJournal);
        $this->glService->expects($this->once())
            ->method('post')
            ->with($mockJournal);

        $transaction = $this->service->postLoan($payload);

        $this->assertInstanceOf(TelebirrTransaction::class, $transaction);
        $this->assertEquals('LOAN', $transaction->tx_type);
        $this->assertEquals(200.00, $transaction->amount);
        $this->assertEquals($agent->id, $transaction->agent_id);
        $this->assertEquals('Posted', $transaction->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_idempotent_requests()
    {
        $agent = TelebirrAgent::factory()->active()->create();
        $existingTransaction = TelebirrTransaction::factory()->create([
            'idempotency_key' => 'duplicate-key',
            'tx_type' => 'ISSUE',
            'agent_id' => $agent->id,
            'amount' => 100.00,
        ]);

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'duplicate-key',
            'agent_short_code' => $agent->short_code,
            'remarks' => 'Duplicate request',
        ];

        $transaction = $this->service->postIssue($payload);

        $this->assertEquals($existingTransaction->id, $transaction->id);
        $this->assertEquals('duplicate-key', $transaction->idempotency_key);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_invalid_agent()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Agent not found: INVALID');

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'test-key',
            'agent_short_code' => 'INVALID',
            'remarks' => 'Test',
        ];

        $this->service->postIssue($payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_inactive_agent()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Agent is not active: INACTIVE');

        $agent = TelebirrAgent::factory()->inactive()->create(['short_code' => 'INACTIVE']);

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'test-key',
            'agent_short_code' => 'INACTIVE',
            'remarks' => 'Test',
        ];

        $this->service->postIssue($payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_invalid_bank_account()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bank account not found or inactive: INVALID');

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'test-key',
            'bank_external_number' => 'INVALID',
        ];

        $this->service->postTopup($payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_zero_amount()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Amount must be positive');

        $payload = [
            'amount' => 0,
            'idempotency_key' => 'test-key',
        ];

        $this->service->postTopup($payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_missing_idempotency_key()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Idempotency key is required');

        $payload = [
            'amount' => 100.00,
        ];

        $this->service->postTopup($payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_reverse_a_journal()
    {
        // Create a posted journal with lines
        $journal = GlJournal::factory()->create(['status' => 'POSTED']);
        GlLine::factory()->create([
            'journal_id' => $journal->id,
            'account_code' => '1000',
            'debit' => 100.00,
            'credit' => 0,
        ]);
        GlLine::factory()->create([
            'journal_id' => $journal->id,
            'account_code' => '2000',
            'debit' => 0,
            'credit' => 100.00,
        ]);

        // Mock GL service methods
        $mockReversingJournal = new GlJournal(['id' => 5, 'status' => 'POSTED']);
        $this->glService->expects($this->once())
            ->method('createJournal')
            ->willReturn($mockReversingJournal);
        $this->glService->expects($this->once())
            ->method('post')
            ->with($mockReversingJournal);

        $reversingJournal = $this->service->reverseJournal($journal->id);

        $this->assertInstanceOf(GlJournal::class, $reversingJournal);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_reversing_unposted_journal()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can only reverse posted journals');

        $journal = GlJournal::factory()->create(['status' => 'DRAFT']);

        $this->service->reverseJournal($journal->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rolls_back_transaction_on_gl_service_failure()
    {
        $agent = TelebirrAgent::factory()->active()->create();

        $payload = [
            'amount' => 100.00,
            'idempotency_key' => 'rollback-test',
            'agent_short_code' => $agent->short_code,
            'remarks' => 'Test rollback',
        ];

        // Mock GL service to throw exception
        $this->glService->expects($this->once())
            ->method('createJournal')
            ->willThrowException(new Exception('GL service error'));

        try {
            $this->service->postIssue($payload);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals('GL service error', $e->getMessage());
        }

        // Verify no transaction was created
        $this->assertDatabaseMissing('telebirr_transactions', [
            'idempotency_key' => 'rollback-test',
        ]);
    }
}