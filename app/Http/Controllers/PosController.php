<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Services\PosService;
use App\Application\Services\ReceiptNumberGeneratorService;
use App\Application\Services\IdempotencyKeyService;
use App\Models\Receipt;

class PosController extends Controller
{
    protected $posService;
    protected $receiptNumberGenerator;
    protected $idempotencyKeyService;

    public function __construct(
        PosService $posService,
        ReceiptNumberGeneratorService $receiptNumberGenerator,
        IdempotencyKeyService $idempotencyKeyService
    ) {
        $this->posService = $posService;
        $this->receiptNumberGenerator = $receiptNumberGenerator;
        $this->idempotencyKeyService = $idempotencyKeyService;
    }

    /**
     * Create a new receipt.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createReceipt(Request $request)
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$this->idempotencyKeyService->validate($idempotencyKey)) {
            return response()->json(['error' => 'Duplicate request'], 409);
        }

        $receiptData = $request->only(['branch_id', 'customer_id', 'currency', 'subtotal', 'tax_total', 'discount_total', 'grand_total', 'paid_total', 'payment_method']);
        $receiptData['number'] = $this->receiptNumberGenerator->generate();
        $lineItems = $request->input('line_items', []);

        $receipt = $this->posService->processReceipt($receiptData, $lineItems);

        return response()->json($receipt, 201);
    }

    /**
     * Void a receipt.
     *
     * @param Request $request
     * @param Receipt $receipt
     * @return \Illuminate\Http\JsonResponse
     */
    public function voidReceipt(Request $request, Receipt $receipt)
    {
        $ctx = [
            'voided_by' => $request->user()->id ?? null,
        ];

        $receipt = $this->posService->voidReceipt($receipt, $ctx);

        return response()->json($receipt);
    }
}