<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Services\PosService;
use App\Application\Services\ReceiptNumberGeneratorService;
use App\Application\Services\IdempotencyKeyService;
use App\Models\Receipt;
use App\Http\Resources\ReceiptResource;
use App\Http\Resources\ReceiptCollection;

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
     * Display a listing of receipts
     */
    public function index(Request $request): ReceiptCollection
    {
        $query = Receipt::with(['customer', 'branch', 'lines.product']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $receipts = $query->orderBy('created_at', 'desc')->paginate(15);

        return new ReceiptCollection($receipts);
    }

    /**
     * Display the specified receipt
     */
    public function show(Receipt $receipt): ReceiptResource
    {
        $receipt->load(['lines.product', 'customer', 'branch']);

        return new ReceiptResource($receipt);
    }

    /**
     * Create a new receipt.
     *
     * @param Request $request
     * @return ReceiptResource
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

        return new ReceiptResource($receipt->load(['lines.product', 'customer', 'branch']));
    }

    /**
     * Void a receipt.
     *
     * @param Request $request
     * @param Receipt $receipt
     * @return ReceiptResource
     */
    public function voidReceipt(Request $request, Receipt $receipt): ReceiptResource
    {
        $ctx = [
            'voided_by' => $request->user()->id ?? null,
        ];

        $receipt = $this->posService->voidReceipt($receipt, $ctx);

        return new ReceiptResource($receipt->load(['lines.product', 'customer', 'branch']));
    }
}