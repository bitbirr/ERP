<?php

namespace App\Services;

use App\Models\TelebirrAgent;
use App\Models\TelebirrTransaction;
use App\Models\BankAccount;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Models\IdempotencyKey;
use App\Services\GL\GlService;
use App\Domain\Audit\AuditLogger;
use App\Exceptions\GlValidationException;
use App\Exceptions\IdempotencyConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Exception;

class TelebirrService
{
    private GlService $glService;
    private AuditLogger $auditLogger;

    public function __construct(GlService $glService, AuditLogger $auditLogger)
    {
        $this->glService = $glService;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Post a TOPUP transaction
     */
    public function postTopup(array $payload): TelebirrTransaction
    {
        return $this->executeTransaction('TOPUP', $payload, function ($payload, $agent, $bankAccount) {
            return $this->createTopupPosting($payload, $bankAccount);
        });
    }

    /**
     * Post an ISSUE transaction
     */
    public function postIssue(array $payload): TelebirrTransaction
    {
        return $this->executeTransaction('ISSUE', $payload, function ($payload, $agent, $bankAccount) {
            return $this->createIssuePosting($payload, $agent);
        });
    }

    /**
     * Post a REPAY transaction
     */
    public function postRepay(array $payload): TelebirrTransaction
    {
        return $this->executeTransaction('REPAY', $payload, function ($payload, $agent, $bankAccount) {
            return $this->createRepayPosting($payload, $agent, $bankAccount);
        });
    }

    /**
     * Post a LOAN transaction
     */
    public function postLoan(array $payload): TelebirrTransaction
    {
        return $this->executeTransaction('LOAN', $payload, function ($payload, $agent, $bankAccount) {
            return $this->createLoanPosting($payload, $agent);
        });
    }

    /**
     * Common transaction execution logic
     */
    private function executeTransaction(string $txType, array $payload, callable $postingResolver): TelebirrTransaction
    {
        // Enhanced idempotency check
        if (isset($payload['idempotency_key'])) {
            $idempotencyService = app(\App\Application\Services\IdempotencyKeyService::class);
            $existing = $idempotencyService->getExistingTransaction($payload['idempotency_key']);

            if ($existing) {
                // Log idempotency hit
                $this->auditLogger->log(
                    'telebirr.transaction.idempotent',
                    Auth::id(),
                    $payload,
                    [
                        'existing_transaction_id' => $existing->id,
                        'idempotency_key' => $payload['idempotency_key']
                    ]
                );

                return $existing;
            }
        }

        return DB::transaction(function () use ($txType, $payload, $postingResolver) {
            // Validate inputs
            $this->validateTransactionPayload($txType, $payload);

            // Resolve entities
            $agent = $this->resolveAgent($payload);
            $bankAccount = $this->resolveBankAccount($payload);

            // Create posting lines
            $posting = $postingResolver($payload, $agent, $bankAccount);

            // Create GL journal
            $journal = $this->createGlJournal($txType, $payload, $posting);

            // Post the journal
            $this->glService->post($journal);

            // Create telebirr transaction record
            $transaction = $this->createTelebirrTransaction($txType, $payload, $agent, $bankAccount, $journal);

            // Clear idempotency cache after successful transaction
            if (isset($payload['idempotency_key'])) {
                $idempotencyService = app(\App\Application\Services\IdempotencyKeyService::class);
                $idempotencyService->clearCache($payload['idempotency_key']);
            }

            // Log audit
            $this->auditLogger->log(
                'telebirr.transaction.created',
                Auth::id(),
                $payload,
                ['transaction_id' => $transaction->id, 'journal_id' => $journal->id]
            );

            // Broadcast event (placeholder)
            // TelebirrTransactionCreated::dispatch($transaction);

            return $transaction;
        });
    }

    /**
     * Validate transaction payload
     */
    private function validateTransactionPayload(string $txType, array $payload): void
    {
        // Basic validations
        if (!isset($payload['amount']) || $payload['amount'] <= 0) {
            throw new Exception('Amount must be positive');
        }

        if (!isset($payload['idempotency_key'])) {
            throw new Exception('Idempotency key is required');
        }

        // Type-specific validations
        if (in_array($txType, ['ISSUE', 'LOAN'])) {
            if (!isset($payload['agent_short_code'])) {
                throw new Exception('Agent short code is required for ' . $txType);
            }
            if (!isset($payload['remarks'])) {
                throw new Exception('Remarks are required for ' . $txType);
            }
        }

        if (in_array($txType, ['REPAY', 'TOPUP'])) {
            if (!isset($payload['bank_external_number'])) {
                throw new Exception('Bank external number is required for ' . $txType);
            }
        }

        if ($txType === 'REPAY') {
            if (!isset($payload['agent_short_code'])) {
                throw new Exception('Agent short code is required for REPAY');
            }
        }
    }

    /**
     * Resolve agent from payload
     */
    private function resolveAgent(array $payload): ?TelebirrAgent
    {
        if (!isset($payload['agent_short_code'])) {
            return null;
        }

        $agent = TelebirrAgent::where('short_code', $payload['agent_short_code'])->first();

        if (!$agent) {
            throw new Exception('Agent not found: ' . $payload['agent_short_code']);
        }

        if ($agent->status !== 'Active') {
            throw new Exception('Agent is not active: ' . $payload['agent_short_code']);
        }

        return $agent;
    }

    /**
     * Resolve bank account from payload
     */
    private function resolveBankAccount(array $payload): ?BankAccount
    {
        if (!isset($payload['bank_external_number'])) {
            return null;
        }

        $bankAccount = BankAccount::where('external_number', $payload['bank_external_number'])
            ->where('is_active', true)
            ->first();

        if (!$bankAccount) {
            throw new Exception('Bank account not found or inactive: ' . $payload['bank_external_number']);
        }

        return $bankAccount;
    }

    /**
     * Create TOPUP posting lines
     */
    private function createTopupPosting(array $payload, BankAccount $bankAccount): array
    {
        $rules = config('telebirr_postings.TOPUP');

        return [
            [
                'account_code' => $bankAccount->glAccount->code,
                'debit' => $payload['amount'],
                'credit' => 0,
                'memo' => 'Topup from bank',
            ],
            [
                'account_code' => $rules['credit_account'],
                'debit' => 0,
                'credit' => $payload['amount'],
                'memo' => 'Topup to distributor',
            ],
        ];
    }

    /**
     * Create ISSUE posting lines
     */
    private function createIssuePosting(array $payload, TelebirrAgent $agent): array
    {
        $rules = config('telebirr_postings.ISSUE');

        return [
            [
                'account_code' => $rules['debit_account'],
                'debit' => $payload['amount'],
                'credit' => 0,
                'memo' => 'Issue to agent: ' . $agent->short_code,
                'meta' => ['agent_id' => $agent->id, 'agent_short_code' => $agent->short_code],
            ],
            [
                'account_code' => $rules['credit_account'],
                'debit' => 0,
                'credit' => $payload['amount'],
                'memo' => 'Issue from distributor',
                'meta' => ['agent_id' => $agent->id, 'agent_short_code' => $agent->short_code],
            ],
        ];
    }

    /**
     * Create REPAY posting lines
     */
    private function createRepayPosting(array $payload, TelebirrAgent $agent, BankAccount $bankAccount): array
    {
        $rules = config('telebirr_postings.REPAY');

        return [
            [
                'account_code' => $rules['debit_account'],
                'debit' => $payload['amount'],
                'credit' => 0,
                'memo' => 'Repayment from agent: ' . $agent->short_code,
                'meta' => ['agent_id' => $agent->id, 'agent_short_code' => $agent->short_code],
            ],
            [
                'account_code' => $bankAccount->glAccount->code,
                'debit' => 0,
                'credit' => $payload['amount'],
                'memo' => 'Repayment to bank',
            ],
        ];
    }

    /**
     * Create LOAN posting lines (same as ISSUE)
     */
    private function createLoanPosting(array $payload, TelebirrAgent $agent): array
    {
        return $this->createIssuePosting($payload, $agent);
    }

    /**
     * Create GL journal
     */
    private function createGlJournal(string $txType, array $payload, array $posting): GlJournal
    {
        $journalData = [
            'source' => 'TELEBIRR',
            'source_id' => null, // Will be set after transaction creation
            'branch_id' => null, // Use default branch
            'date' => now()->toDateString(),
            'memo' => $this->generateJournalMemo($txType, $payload),
            'status' => 'DRAFT',
        ];

        $journal = $this->glService->createJournal($journalData);

        // Create lines
        $lineNo = 1;
        foreach ($posting as $line) {
            GlLine::create([
                'journal_id' => $journal->id,
                'line_no' => $lineNo++,
                'account_code' => $line['account_code'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'memo' => $line['memo'],
                'meta' => $line['meta'] ?? null,
            ]);
        }

        return $journal;
    }

    /**
     * Create telebirr transaction record
     */
    private function createTelebirrTransaction(
        string $txType,
        array $payload,
        ?TelebirrAgent $agent,
        ?BankAccount $bankAccount,
        GlJournal $journal
    ): TelebirrTransaction {
        return TelebirrTransaction::create([
            'tx_type' => $txType,
            'agent_id' => $agent?->id,
            'bank_account_id' => $bankAccount?->id,
            'amount' => $payload['amount'],
            'currency' => $payload['currency'] ?? 'ETB',
            'idempotency_key' => $payload['idempotency_key'],
            'gl_journal_id' => $journal->id,
            'status' => 'Posted',
            'remarks' => $payload['remarks'] ?? null,
            'external_ref' => $payload['external_ref'] ?? null,
            'created_by' => Auth::id(),
            'posted_at' => now(),
        ]);
    }

    /**
     * Generate journal memo
     */
    private function generateJournalMemo(string $txType, array $payload): string
    {
        $memo = "Telebirr {$txType}";

        if (isset($payload['agent_short_code'])) {
            $memo .= " - Agent: {$payload['agent_short_code']}";
        }

        if (isset($payload['external_ref'])) {
            $memo .= " - Ref: {$payload['external_ref']}";
        }

        return $memo;
    }

    /**
     * Reverse a GL journal (for voiding transactions)
     */
    public function reverseJournal(int $journalId): GlJournal
    {
        $originalJournal = GlJournal::findOrFail($journalId);

        if ($originalJournal->status !== 'POSTED') {
            throw new Exception('Can only reverse posted journals');
        }

        return DB::transaction(function () use ($originalJournal) {
            // Create reversing journal
            $reversingJournal = $this->glService->createJournal([
                'source' => 'TELEBIRR',
                'source_id' => $originalJournal->source_id,
                'branch_id' => $originalJournal->branch_id,
                'date' => now()->toDateString(),
                'memo' => 'Reversal of: ' . $originalJournal->memo,
                'status' => 'DRAFT',
            ]);

            // Create reversing lines (debit becomes credit and vice versa)
            $lineNo = 1;
            foreach ($originalJournal->lines as $originalLine) {
                GlLine::create([
                    'journal_id' => $reversingJournal->id,
                    'line_no' => $lineNo++,
                    'account_code' => $originalLine->account_code,
                    'debit' => $originalLine->credit, // Swap debit/credit
                    'credit' => $originalLine->debit,
                    'memo' => 'Reversal: ' . $originalLine->memo,
                    'meta' => $originalLine->meta,
                ]);
            }

            // Post the reversing journal
            $this->glService->post($reversingJournal);

            return $reversingJournal;
        });
    }
}