<?php

namespace App\Application\Services;

use App\Domain\Audit\AuditLogger;
use App\Events\TransactionCreated;
use App\Models\Transaction; // create if not present
use Illuminate\Validation\ValidationException;

class TransactionService extends BaseService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(array $data): Transaction
    {
        // Very light inline validation (replace with FormRequest)
        if (!isset($data['amount'], $data['tx_type'], $data['channel'])) {
            throw ValidationException::withMessages(['payload' => 'Missing required fields.']);
        }

        return $this->transaction(function () use ($data) {
            $tx = Transaction::create($data);
            $this->dispatchEvent(new TransactionCreated(['id' => $tx->id] + $data));
            $this->audit->log('tx.created', $tx, null, $tx->toArray());
            return $tx;
        });
    }
}
