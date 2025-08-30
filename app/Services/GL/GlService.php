<?php

namespace App\Services\GL;

use App\Models\GlJournal;
use App\Models\GlLine;
use App\Models\GlAccount;
use App\Models\IdempotencyKey;
use App\Exceptions\GlValidationException;
use App\Exceptions\GlPostingLockedException;
use App\Exceptions\IdempotencyConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Closure;

class GlService
{
    /**
     * Validate a draft journal before posting
     */
    public function validateDraft(GlJournal $journal): array
    {
        $errors = [];

        // Check if journal is in draft status
        if (!$journal->isDraft()) {
            $errors[] = 'Journal must be in DRAFT status to be validated';
        }

        // Check minimum lines
        if (!$journal->hasMinimumLines()) {
            $errors[] = 'Journal must have at least 2 lines';
        }

        // Check balance
        if (!$journal->validateBalance()) {
            $totalDebit = $journal->getTotalDebit();
            $totalCredit = $journal->getTotalCredit();
            $errors[] = "Journal debits ({$totalDebit}) must equal credits ({$totalCredit})";
        }

        // Validate each line
        foreach ($journal->lines as $line) {
            $lineErrors = $this->validateLine($line);
            if (!empty($lineErrors)) {
                $errors = array_merge($errors, $lineErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate a single journal line
     */
    private function validateLine(GlLine $line): array
    {
        $errors = [];

        // Check amounts
        if (!$line->hasAmount()) {
            $errors[] = "Line {$line->line_no} must have either debit or credit amount";
        }

        if (!$line->isValid()) {
            $errors[] = "Line {$line->line_no} cannot have both debit and credit amounts";
        }

        // Check account exists and is postable
        if (!$line->account) {
            $errors[] = "Line {$line->line_no} references non-existent account";
        } elseif (!$line->account->is_postable) {
            $errors[] = "Line {$line->line_no} references non-postable account '{$line->account->code}'";
        }

        return $errors;
    }

    /**
     * Post a journal with optional idempotency
     */
    public function post(GlJournal $journal, ?string $idempotencyScopeKey = null): void
    {
        // Handle idempotency if provided
        if ($idempotencyScopeKey) {
            [$scope, $key] = explode(':', $idempotencyScopeKey, 2);
            $this->withIdempotency(
                function () use ($journal) {
                    return $this->postInternal($journal);
                },
                $scope,
                $key
            );
            return;
        }

        $this->postInternal($journal);
    }

    /**
     * Internal posting logic
     */
    private function postInternal(GlJournal $journal): void
    {
        // Validate journal
        $errors = $this->validateDraft($journal);
        if (!empty($errors)) {
            throw new GlValidationException('Journal validation failed', $errors);
        }

        DB::transaction(function () use ($journal) {
            // Lock the journal for update
            $journal = $journal->lockForUpdate()->find($journal->id);

            if (!$journal) {
                throw new GlPostingLockedException('Journal not found or locked', $journal->id ?? '');
            }

            if (!$journal->isDraft()) {
                throw new GlValidationException('Journal is not in DRAFT status');
            }

            // Update journal status
            $journal->update([
                'status' => 'POSTED',
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            // Update account balances (if using balance table)
            $this->updateAccountBalances($journal);
        });

        // Fire events
        // JournalPosted::dispatch($journal);
    }

    /**
     * Reverse a posted journal
     */
    public function reverse(GlJournal $journal, string $reason): GlJournal
    {
        if (!$journal->isPosted()) {
            throw new GlValidationException('Only posted journals can be reversed');
        }

        return DB::transaction(function () use ($journal, $reason) {
            // Create reversing journal
            $reversingJournal = $this->createReversingJournal($journal, $reason);

            // Mark original as reversed
            $journal->update(['status' => 'REVERSED']);

            // Link journals
            $journal->update(['external_ref' => $reversingJournal->id]);
            $reversingJournal->update(['external_ref' => $journal->id]);

            return $reversingJournal;
        });
    }

    /**
     * Void a draft journal
     */
    public function void(GlJournal $journal, string $reason): void
    {
        if (!$journal->isDraft()) {
            throw new GlValidationException('Only draft journals can be voided');
        }

        $journal->update([
            'status' => 'VOIDED',
            'memo' => ($journal->memo ? $journal->memo . ' | ' : '') . "VOIDED: {$reason}"
        ]);
    }

    /**
     * Create a new journal from DTO
     */
    public function createJournal(array $dto): GlJournal
    {
        return DB::transaction(function () use ($dto) {
            $journal = GlJournal::create([
                'journal_no' => $dto['journal_no'] ?? $this->generateJournalNumber(),
                'journal_date' => $dto['journal_date'] ?? now()->toDateString(),
                'currency' => $dto['currency'] ?? config('accounting.base_currency'),
                'fx_rate' => $dto['fx_rate'] ?? 1.0,
                'source' => $dto['source'] ?? 'MANUAL',
                'reference' => $dto['reference'] ?? null,
                'memo' => $dto['memo'] ?? null,
                'branch_id' => $dto['branch_id'] ?? null,
                'status' => 'DRAFT',
            ]);

            // Create lines
            if (isset($dto['lines'])) {
                $this->createJournalLines($journal, $dto['lines']);
            }

            return $journal;
        });
    }

    /**
     * Execute function with idempotency
     */
    public function withIdempotency(callable $fn, string $scope, string $key, ?Closure $snapshotResolver = null): mixed
    {
        $requestHash = $this->generateRequestHash($scope, $key);

        DB::transaction(function () use (&$result, $fn, $scope, $key, $requestHash, $snapshotResolver) {
            // Try to acquire lock
            $idempotencyKey = IdempotencyKey::acquireLock($scope, $key);

            if (!$idempotencyKey) {
                // Check if already completed
                $existing = IdempotencyKey::findByScopeAndKey($scope, $key);

                if ($existing && $existing->isSucceeded()) {
                    $result = $existing->response_snapshot;
                    return;
                }

                throw new GlPostingLockedException("Idempotency key '{$scope}:{$key}' is locked", '', 0);
            }

            // Check for hash conflict
            if ($idempotencyKey->request_hash && $idempotencyKey->request_hash !== $requestHash) {
                throw new IdempotencyConflictException('', $scope, $key, $idempotencyKey->response_snapshot);
            }

            // Execute the function
            try {
                $result = $fn();

                // Store result
                $snapshot = $snapshotResolver ? $snapshotResolver($result) : $result;
                $idempotencyKey->markSucceeded($snapshot);

            } catch (\Exception $e) {
                $idempotencyKey->markFailed(['error' => $e->getMessage()]);
                throw $e;
            }
        });

        return $result;
    }

    /**
     * Create reversing journal
     */
    private function createReversingJournal(GlJournal $original, string $reason): GlJournal
    {
        $reversingLines = $original->lines->map(function ($line) {
            return [
                'account_id' => $line->account_id,
                'branch_id' => $line->branch_id,
                'cost_center_id' => $line->cost_center_id,
                'project_id' => $line->project_id,
                'customer_id' => $line->customer_id,
                'supplier_id' => $line->supplier_id,
                'item_id' => $line->item_id,
                'memo' => $line->memo,
                'debit' => $line->credit,  // Swap debit/credit
                'credit' => $line->debit,
            ];
        })->toArray();

        return $this->createJournal([
            'journal_no' => $this->generateReversingJournalNumber($original),
            'journal_date' => now()->toDateString(),
            'currency' => $original->currency,
            'fx_rate' => $original->fx_rate,
            'source' => 'MANUAL',
            'reference' => "REVERSAL: {$original->journal_no}",
            'memo' => "Reversal of {$original->journal_no}: {$reason}",
            'branch_id' => $original->branch_id,
            'lines' => $reversingLines,
        ]);
    }

    /**
     * Create journal lines
     */
    private function createJournalLines(GlJournal $journal, array $lines): void
    {
        foreach ($lines as $lineData) {
            GlLine::create(array_merge($lineData, [
                'journal_id' => $journal->id,
            ]));
        }
    }

    /**
     * Update account balances
     */
    private function updateAccountBalances(GlJournal $journal): void
    {
        // This would update the gl_account_balances table if implemented
        // For now, balances are calculated on-the-fly from posted journals
    }

    /**
     * Generate unique journal number
     */
    private function generateJournalNumber(): string
    {
        $date = now()->format('Ymd');
        $sequence = GlJournal::where('journal_no', 'like', "{$date}%")
            ->max('journal_no') ?? "{$date}000";

        $number = intval(substr($sequence, 8)) + 1;
        return $date . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate reversing journal number
     */
    private function generateReversingJournalNumber(GlJournal $original): string
    {
        return $original->journal_no . '-REV';
    }

    /**
     * Generate request hash for idempotency
     */
    private function generateRequestHash(string $scope, string $key): string
    {
        return hash('sha256', $scope . ':' . $key . ':' . json_encode(request()->all()));
    }
}