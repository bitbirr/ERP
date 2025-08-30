<?php

namespace App\Application\Services;

use App\Models\Receipt;
use InvalidArgumentException;

class ReceiptStatusService
{
    const STATUS_DRAFT = 'draft';
    const STATUS_POSTED = 'posted';
    const STATUS_VOIDED = 'voided';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Validate and change the status of a receipt.
     *
     * @param Receipt $receipt
     * @param string $newStatus
     * @return Receipt
     * @throws InvalidArgumentException
     */
    public function changeStatus(Receipt $receipt, string $newStatus)
    {
        $this->validateStatusTransition($receipt->status, $newStatus);

        $receipt->status = $newStatus;
        $receipt->save();

        return $receipt;
    }

    /**
     * Validate the status transition.
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @throws InvalidArgumentException
     */
    protected function validateStatusTransition(string $currentStatus, string $newStatus)
    {
        $validTransitions = [
            self::STATUS_DRAFT => [self::STATUS_POSTED, self::STATUS_VOIDED],
            self::STATUS_POSTED => [self::STATUS_REFUNDED, self::STATUS_VOIDED],
            self::STATUS_VOIDED => [],
            self::STATUS_REFUNDED => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new InvalidArgumentException("Invalid status transition from $currentStatus to $newStatus.");
        }
    }
}