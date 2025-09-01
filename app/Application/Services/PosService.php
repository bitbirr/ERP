<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\GlAccount;

use Illuminate\Support\Collection;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Models\StockMovement;
use App\Models\AuditLog;
use App\Application\Services\InventoryService;
use App\Domain\Audit\AuditLogger;
use App\Services\GL\GlService;

class PosService
{
    protected $inventoryService;
    protected $auditLogger;
    protected $glService;

    public function __construct(
        InventoryService $inventoryService,
        GlService $glService,
        ?AuditLogger $auditLogger = null
    ) {
        $this->inventoryService = $inventoryService;
        $this->glService = $glService;
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
    /**
     * Execute comprehensive end-to-end POS workflow
     */
    public function executePosWorkflow(array $receiptData, array $lineItems): array
    {
        return DB::transaction(function () use ($receiptData, $lineItems) {
            $workflowResult = [
                'receipt' => null,
                'stock_movements' => [],
                'gl_journal' => null,
                'audit_entries' => [],
                'workflow_status' => 'in_progress'
            ];

            try {
                // Step 1: Process POS Receipt
                $receipt = $this->processReceipt($receiptData, $lineItems);
                $workflowResult['receipt'] = $receipt;

                // Step 2: Handle Stock Movements (already done in processReceipt)
                $stockMovements = StockMovement::where('ref', $receipt->id)->get();
                $workflowResult['stock_movements'] = $stockMovements;

                // Step 3: GL Journal Creation (already done in processReceipt)
                if (config('accounting.pos_posting.enabled', true)) {
                    $journal = GlJournal::where('reference', 'RECEIPT-' . $receipt->number)->first();
                    $workflowResult['gl_journal'] = $journal;
                }

                // Step 4: Audit Trail (already established)
                $auditEntries = AuditLog::where('subject_type', Receipt::class)
                    ->where('subject_id', $receipt->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                $workflowResult['audit_entries'] = $auditEntries;

                $workflowResult['workflow_status'] = 'completed';

                // Final audit of the complete workflow
                $this->auditLogger->log(
                    'pos.workflow.completed',
                    $receipt,
                    null,
                    $workflowResult,
                    [
                        'workflow_steps_completed' => [
                            'receipt_processed' => true,
                            'stock_issued' => $stockMovements->count() > 0,
                            'gl_posted' => $workflowResult['gl_journal'] !== null,
                            'audit_trail_established' => $auditEntries->count() > 0,
                        ],
                        'total_workflow_time' => now()->diffInMilliseconds($receipt->created_at),
                    ]
                );

                return $workflowResult;

            } catch (\Exception $e) {
                $workflowResult['workflow_status'] = 'failed';
                $workflowResult['error'] = $e->getMessage();

                $this->auditLogger->log(
                    'pos.workflow.failed',
                    null,
                    null,
                    $workflowResult,
                    ['error_details' => $e->getTraceAsString()]
                );

                throw $e;
            }
        });
    }

    public function processReceipt(array $receiptData, array $lineItems)
    {
        return DB::transaction(function () use ($receiptData, $lineItems) {
            $receiptData = $this->calculateReceiptTotals($lineItems, $receiptData);
            $receiptData['number'] = $this->generateReceiptNumber($receiptData['branch_id']);
            $receipt = Receipt::create($receiptData);
            $receipt->refresh(); // Ensure we have the latest data from DB

            foreach ($lineItems as $index => $lineItem) {
                $lineItem = $this->calculateLineTotals($lineItem);
                $lineItem['product_id'] = $lineItem['product']->id;
                $lineItem['receipt_id'] = $receipt->id;

                // Generate unique stock movement reference for this line
                $stockMovementRef = $receipt->id . '-L' . $index;

                $lineItem['stock_movement_ref'] = $stockMovementRef;
                ReceiptLine::create($lineItem);

                $this->inventoryService->issueStock(
                    $lineItem['product'],
                    $lineItem['branch'],
                    $lineItem['qty'],
                    $stockMovementRef,
                    ['created_by' => $receiptData['created_by']]
                );
            }

            // Conditionally create GL Journal entry
            $journal = null;
            if (config('accounting.pos_posting.enabled', true)) {
                $journal = $this->createPosJournal($receipt, $lineItems, $receiptData);
            }

            // Log the receipt creation action with comprehensive audit trail
            $this->auditLogger->log(
                'pos.receipt.created',
                $receipt,
                null,
                array_merge($receiptData, [
                    'line_items_count' => count($lineItems),
                    'gl_journal_created' => $journal ? $journal->id : null,
                    'stock_movements_created' => count($lineItems),
                ]),
                [
                    'created_by' => $receiptData['created_by'] ?? null,
                    'branch_id' => $receiptData['branch_id'] ?? null,
                    'workflow_steps' => [
                        'receipt_created' => true,
                        'stock_issued' => true,
                        'gl_posted' => $journal !== null,
                        'audit_logged' => true,
                    ]
                ]
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
            if ($receipt->status !== 'POSTED') {
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

            // Conditionally reverse GL if enabled
            $reversingJournal = null;
            if (config('accounting.pos_posting.enabled', true)) {
                $originalJournal = GlJournal::where('reference', 'RECEIPT-' . $receipt->number)->first();
                if ($originalJournal) {
                    $reversingJournal = $this->glService->reverse($originalJournal, 'Void POS Receipt #' . $receipt->number);
                }
            }

            // Update receipt
            $receipt->update([
                'status' => 'VOIDED',
                'voided_at' => now(),
                'voided_by' => $ctx['voided_by'] ?? null,
            ]);

            // Enhanced audit logging for void operation
            $this->auditLogger->log(
                'pos.receipt.voided',
                $receipt,
                $receipt->toArray(),
                $receipt->fresh()->toArray(),
                array_merge($ctx, [
                    'stock_reversed_count' => $receipt->lines->count(),
                    'gl_journal_reversed' => $reversingJournal ? $reversingJournal->id : null,
                    'workflow_steps' => [
                        'stock_reversed' => true,
                        'gl_reversed' => $reversingJournal !== null,
                        'receipt_status_updated' => true,
                        'audit_logged' => true,
                    ]
                ])
            );

            return $receipt->fresh();
        });
    }

    /**
     * Create GL journal for POS receipt with proper accounting entries
     */
    protected function createPosJournal(Receipt $receipt, array $lineItems, array $receiptData): GlJournal
    {
        $rules = config('accounting.pos_posting.rules', []);

        // Get GL accounts
        $salesAccount = GlAccount::where('code', $rules['sales_revenue'] ?? '4000')->first();
        $cashAccount = GlAccount::where('code', $rules['cash_receipt'] ?? '1001')->first();
        $taxAccount = GlAccount::where('code', $rules['tax_payable'] ?? '2001')->first();
        $discountAccount = GlAccount::where('code', $rules['discount_expense'] ?? '5001')->first();

        if (!$salesAccount || !$cashAccount) {
            throw new \Exception('Required GL accounts not found for POS posting');
        }

        $journalData = [
            'journal_no' => $this->generateJournalNumber(),
            'journal_date' => now()->toDateString(),
            'currency' => $receiptData['currency'] ?? config('accounting.base_currency'),
            'fx_rate' => 1.0,
            'source' => 'POS',
            'reference' => 'RECEIPT-' . $receipt->number,
            'memo' => 'POS Receipt #' . $receipt->number,
            'branch_id' => $receipt->branch_id,
            'status' => 'DRAFT',
        ];

        $journal = $this->glService->createJournal($journalData);

        $lines = [];

        // Debit Cash/Bank account
        $lines[] = [
            'account_id' => $cashAccount->id,
            'debit' => $receiptData['paid_total'] ?? $receiptData['grand_total'],
            'credit' => 0,
            'memo' => 'Cash receipt for POS transaction',
        ];

        // Credit Sales Revenue
        $lines[] = [
            'account_id' => $salesAccount->id,
            'debit' => 0,
            'credit' => $receiptData['subtotal'],
            'memo' => 'Sales revenue',
        ];

        // Handle tax if applicable
        if (($receiptData['tax_total'] ?? 0) > 0 && $taxAccount) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'debit' => 0,
                'credit' => $receiptData['tax_total'],
                'memo' => 'Sales tax collected',
            ];
        }

        // Handle discount if applicable
        if (($receiptData['discount_total'] ?? 0) > 0 && $discountAccount) {
            $lines[] = [
                'account_id' => $discountAccount->id,
                'debit' => $receiptData['discount_total'],
                'credit' => 0,
                'memo' => 'Sales discount',
            ];
        }

        // Create journal lines
        foreach ($lines as $lineData) {
            GlLine::create(array_merge($lineData, ['journal_id' => $journal->id]));
        }

        // Post the journal immediately for POS transactions
        $this->glService->post($journal);

        return $journal;
    }

    /**
     * Generate unique journal number for POS
     */
    protected function generateJournalNumber(): string
    {
        $date = now()->format('Ymd');
        $lastJournal = GlJournal::where('journal_no', 'like', "POS{$date}%")
            ->orderBy('journal_no', 'desc')
            ->first();

        $sequence = $lastJournal ? intval(substr($lastJournal->journal_no, 11)) + 1 : 1;
        return 'POS' . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}