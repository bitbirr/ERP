<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Receipt;
use App\Models\ReceiptLine;

use Illuminate\Support\Collection;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Application\Services\InventoryService;
use App\Domain\Audit\AuditLogger;

class PosService
{
    protected $inventoryService;
    protected $auditLogger;

    public function __construct(InventoryService $inventoryService, ?AuditLogger $auditLogger = null)
    {
        $this->inventoryService = $inventoryService;
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }

     /* Process a new receipt transaction.
     *
     * @param array $receiptData
     * @param array $lineItems
     * @return Receipt
     */
     /*
     * @param array $receiptData
     * @param array $lineItems
     * @return Receipt
     */
    public function processReceipt(array $receiptData, array $lineItems)
    {
        return DB::transaction(function () use ($receiptData, $lineItems) {
            $receiptData = $this->calculateReceiptTotals($lineItems, $receiptData);
            $receiptData['number'] = $this->generateReceiptNumber($receiptData['branch_id']);
            $receipt = Receipt::create($receiptData);
            $receipt->refresh(); // Ensure we have the latest data from DB

            foreach ($lineItems as $lineItem) {
                $lineItem = $this->calculateLineTotals($lineItem);
                $lineItem['product_id'] = $lineItem['product']->id;
                $lineItem['receipt_id'] = $receipt->id;
                ReceiptLine::create($lineItem);
                $this->inventoryService->issueStock(
                    $lineItem['product'],
                    $lineItem['branch'],
                    $lineItem['qty'],
                    $receipt->id,
                    ['created_by' => $receiptData['created_by']]
                );
            }

            // Create GL Journal entry
            $journal = GlJournal::create([
                'journal_number' => uniqid('JN-'),
                'date' => now(),
                'description' => 'Receipt #' . $receipt->id,
                'total_debit' => $receiptData['grand_total'],
                'total_credit' => $receiptData['grand_total'],
            ]);

            // Create GL Line entries
            foreach ($lineItems as $lineItem) {
                GlLine::create([
                    'journal_id' => $journal->id,
                    'account_id' => $lineItem['account_id'], // Assuming account_id is part of lineItem
                    'debit' => $lineItem['line_total'],
                    'credit' => 0,
                    'description' => 'Line item for product ' . $lineItem['product'],
                ]);
            }

            // Log the receipt creation action
            $this->auditLogger->log(
                'create_receipt',
                $receipt,
                null,
                $receiptData,
                ['created_by' => $receiptData['created_by']]
            );

            return $receipt;
        });
    
    
    }
    
    /**
     * Calculate totals for a receipt.
     *
     * @param array $lineItems
     * @param array $receiptData
     * @return array
     */
    protected function calculateReceiptTotals(array $lineItems, array $receiptData)
    {
        $subtotal = 0;
        $taxTotal = 0;
        $discountTotal = 0;

        foreach ($lineItems as $lineItem) {
            $subtotal += $lineItem['qty'] * $lineItem['price'];
            $taxTotal += $lineItem['tax_amount'];
            $discountTotal += $lineItem['discount'];
        }

        $receiptData['subtotal'] = $subtotal;
        $receiptData['tax_total'] = $taxTotal;
        $receiptData['discount_total'] = $discountTotal;
        $receiptData['grand_total'] = $subtotal + $taxTotal - $discountTotal;

        return $receiptData;
    }

    /**
     * Calculate totals for a line item.
     *
     * @param array $lineItem
     * @return array
     */
    protected function calculateLineTotals(array $lineItem)
    {
        $lineItem['line_total'] = ($lineItem['qty'] * $lineItem['price']) + $lineItem['tax_amount'] - $lineItem['discount'];
        return $lineItem;
    }

    /**
     * Generate a unique receipt number for the branch.
     *
     * @param string $branchId
     * @return string
     */
    protected function generateReceiptNumber(string $branchId): string
    {
        $date = now()->format('Ymd');
        $lastReceipt = Receipt::where('branch_id', $branchId)
            ->where('number', 'like', "{$date}%")
            ->orderBy('number', 'desc')
            ->first();

        $sequence = $lastReceipt ? intval(substr($lastReceipt->number, 8)) + 1 : 1;
        return $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Void a receipt: reverse stock, GL, update status, audit.
     *
     * @param Receipt $receipt
     * @param array $ctx
     * @return Receipt
     */
    public function voidReceipt(Receipt $receipt, array $ctx = [])
    {
        return DB::transaction(function () use ($receipt, $ctx) {
            if ($receipt->status !== 'posted') {
                throw new \Exception('Only posted receipts can be voided');
            }

            // Reverse stock movements
            foreach ($receipt->lines as $line) {
                $this->inventoryService->receiveStock(
                    $line->product,
                    $receipt->branch,
                    $line->qty,
                    $receipt->id . '-void',
                    ['created_by' => $ctx['voided_by'] ?? null]
                );
            }

            // Reverse GL: Create reversing journal
            $originalJournal = GlJournal::where('description', 'Receipt #' . $receipt->id)->first();
            if ($originalJournal) {
                $reversingJournal = GlJournal::create([
                    'journal_number' => 'REV-' . $originalJournal->journal_number,
                    'date' => now(),
                    'description' => 'Void Receipt #' . $receipt->id,
                    'total_debit' => $originalJournal->total_credit,
                    'total_credit' => $originalJournal->total_debit,
                ]);

                foreach ($originalJournal->lines as $line) {
                    GlLine::create([
                        'journal_id' => $reversingJournal->id,
                        'account_id' => $line->account_id,
                        'debit' => $line->credit,
                        'credit' => $line->debit,
                        'description' => 'Reversing: ' . $line->description,
                    ]);
                }
            }

            // Update receipt
            $receipt->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $ctx['voided_by'] ?? null,
            ]);

            // Audit
            $this->auditLogger->log(
                'void_receipt',
                $receipt,
                $receipt->toArray(),
                $receipt->fresh()->toArray(),
                $ctx
            );

            return $receipt->fresh();
        });
    }
}