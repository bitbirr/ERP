<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Application\Services\InventoryService;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a listing of inventory items.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryItem::with(['product', 'branch']);

        // Apply filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('has_stock')) {
            $hasStock = $request->boolean('has_stock');
            if ($hasStock) {
                $query->where('on_hand', '>', 0);
            } else {
                $query->where('on_hand', '=', 0);
            }
        }

        $inventory = $query->orderBy('product_id')->orderBy('branch_id')->paginate(50);

        return response()->json($inventory);
    }

    /**
     * Display the specified inventory item.
     */
    public function show(Branch $branch, Product $product): JsonResponse
    {
        $inventoryItem = InventoryItem::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->with(['product', 'branch'])
            ->first();

        if (!$inventoryItem) {
            return response()->json(['message' => 'Inventory item not found'], 404);
        }

        return response()->json($inventoryItem);
    }

    /**
     * Set opening balance for a product at a branch.
     */
    public function opening(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'qty' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $branch = Branch::findOrFail($request->branch_id);

            $inventoryItem = $this->inventoryService->openingBalance(
                $product,
                $branch,
                $request->qty,
                $request->input('context', [])
            );

            return response()->json($inventoryItem, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Receive stock for a product at a branch.
     */
    public function receive(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'qty' => 'required|numeric|min:0.01',
            'ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $branch = Branch::findOrFail($request->branch_id);

            $inventoryItem = $this->inventoryService->receiveStock(
                $product,
                $branch,
                $request->qty,
                $request->ref,
                $request->input('context', [])
            );

            return response()->json($inventoryItem);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reserve stock for a product at a branch.
     */
    public function reserve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'qty' => 'required|numeric|min:0.01',
            'ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $branch = Branch::findOrFail($request->branch_id);

            $inventoryItem = $this->inventoryService->reserve(
                $product,
                $branch,
                $request->qty,
                $request->ref,
                $request->input('context', [])
            );

            return response()->json($inventoryItem);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Unreserve stock for a product at a branch.
     */
    public function unreserve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'qty' => 'required|numeric|min:0.01',
            'ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $branch = Branch::findOrFail($request->branch_id);

            $inventoryItem = $this->inventoryService->unreserve(
                $product,
                $branch,
                $request->qty,
                $request->ref,
                $request->input('context', [])
            );

            return response()->json($inventoryItem);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Issue stock for a product at a branch.
     */
    public function issue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'qty' => 'required|numeric|min:0.01',
            'ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $branch = Branch::findOrFail($request->branch_id);

            $inventoryItem = $this->inventoryService->issueStock(
                $product,
                $branch,
                $request->qty,
                $request->ref,
                $request->input('context', [])
            );

            return response()->json($inventoryItem);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Transfer stock between branches.
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'required|exists:branches,id',
            'qty' => 'required|numeric|min:0.01',
            'ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->from_branch_id === $request->to_branch_id) {
            return response()->json(['message' => 'Cannot transfer to the same branch'], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $fromBranch = Branch::findOrFail($request->from_branch_id);
            $toBranch = Branch::findOrFail($request->to_branch_id);

            $this->inventoryService->transfer(
                $product,
                $fromBranch,
                $toBranch,
                $request->qty,
                $request->ref,
                $request->input('context', [])
            );

            return response()->json(['message' => 'Stock transferred successfully']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Adjust stock levels for a product at a branch.
     */
    public function adjust(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'qty' => 'required|numeric',
            'reason' => 'nullable|string|max:255',
            'ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);
            $branch = Branch::findOrFail($request->branch_id);

            $inventoryItem = $this->inventoryService->adjust(
                $product,
                $branch,
                $request->qty,
                $request->reason,
                $request->ref,
                $request->input('context', [])
            );

            return response()->json($inventoryItem);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Bulk receive stock for multiple products at a branch.
     */
    public function bulkReceive(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $branch = Branch::findOrFail($request->branch_id);
            $results = [];
            $errors = [];

            DB::transaction(function () use ($request, $branch, &$results, &$errors) {
                foreach ($request->items as $index => $item) {
                    try {
                        $product = Product::findOrFail($item['product_id']);

                        $inventoryItem = $this->inventoryService->receiveStock(
                            $product,
                            $branch,
                            $item['qty'],
                            $item['ref'] ?? null,
                            $request->input('context', [])
                        );

                        $results[] = [
                            'index' => $index,
                            'product_id' => $item['product_id'],
                            'qty' => $item['qty'],
                            'inventory_item' => $inventoryItem
                        ];
                    } catch (\Exception $e) {
                        $errors[] = [
                            'index' => $index,
                            'product_id' => $item['product_id'],
                            'error' => $e->getMessage()
                        ];
                        throw $e; // Re-throw to rollback transaction
                    }
                }
            });

            if (!empty($errors)) {
                return response()->json([
                    'message' => 'Bulk receive partially failed',
                    'successful' => $results,
                    'failed' => $errors
                ], 422);
            }

            return response()->json([
                'message' => 'Bulk receive completed successfully',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Bulk reserve stock for multiple products at a branch.
     */
    public function bulkReserve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $branch = Branch::findOrFail($request->branch_id);
            $results = [];
            $errors = [];

            DB::transaction(function () use ($request, $branch, &$results, &$errors) {
                foreach ($request->items as $index => $item) {
                    try {
                        $product = Product::findOrFail($item['product_id']);

                        $inventoryItem = $this->inventoryService->reserve(
                            $product,
                            $branch,
                            $item['qty'],
                            $item['ref'] ?? null,
                            $request->input('context', [])
                        );

                        $results[] = [
                            'index' => $index,
                            'product_id' => $item['product_id'],
                            'qty' => $item['qty'],
                            'inventory_item' => $inventoryItem
                        ];
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                        $errors[] = [
                            'index' => $index,
                            'product_id' => $item['product_id'],
                            'error' => $e->getMessage(),
                            'status_code' => $e->getStatusCode()
                        ];
                        throw $e; // Re-throw to rollback transaction
                    } catch (\Exception $e) {
                        $errors[] = [
                            'index' => $index,
                            'product_id' => $item['product_id'],
                            'error' => $e->getMessage()
                        ];
                        throw $e; // Re-throw to rollback transaction
                    }
                }
            });

            if (!empty($errors)) {
                // Check if any errors were HttpExceptions with 409 status
                $hasConcurrencyErrors = collect($errors)->contains(function ($error) {
                    return isset($error['status_code']) && $error['status_code'] === 409;
                });

                $statusCode = $hasConcurrencyErrors ? 409 : 422;

                return response()->json([
                    'message' => 'Bulk reserve partially failed',
                    'successful' => $results,
                    'failed' => $errors
                ], $statusCode);
            }

            return response()->json([
                'message' => 'Bulk reserve completed successfully',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get stock on-hand report.
     * Sums by product/branch; includes reserved/available; filterable by branch and type.
     */
    public function stockOnHand(Request $request): JsonResponse
    {
        $query = InventoryItem::with(['product', 'branch'])
            ->select([
                'inventory_items.product_id',
                'inventory_items.branch_id',
                'inventory_items.on_hand',
                'inventory_items.reserved',
                DB::raw('(inventory_items.on_hand - inventory_items.reserved) as available')
            ]);

        // Filter by branch
        if ($request->has('branch')) {
            $query->where('inventory_items.branch_id', $request->branch);
        }

        // Filter by product type
        if ($request->has('type')) {
            $query->join('products', 'inventory_items.product_id', '=', 'products.id')
                ->where('products.type', $request->type);
        }

        // Group by product and branch, sum quantities
        $results = $query->get()
            ->groupBy(['product_id', 'branch_id'])
            ->map(function ($items) {
                $item = $items->first();
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_code' => $item->product->code,
                    'product_type' => $item->product->type,
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch->name,
                    'on_hand' => $items->sum('on_hand'),
                    'reserved' => $items->sum('reserved'),
                    'available' => $items->sum('available')
                ];
            })
            ->values();

        return response()->json([
            'data' => $results,
            'total' => $results->count()
        ]);
    }

    /**
     * Get stock movements report.
     * Paginated ledger; totals by movement_type.
     */
    public function stockMovements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'branch'])
            ->select([
                'stock_movements.id',
                'stock_movements.product_id',
                'stock_movements.branch_id',
                'stock_movements.qty',
                'stock_movements.type',
                'stock_movements.ref',
                'stock_movements.created_at',
                'stock_movements.created_by'
            ]);

        // Filter by date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by branch
        if ($request->has('branch')) {
            $query->where('branch_id', $request->branch);
        }

        // Get paginated results
        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        // Calculate totals by movement type
        $totals = StockMovement::select('type', DB::raw('SUM(qty) as total_qty'), DB::raw('COUNT(*) as count'))
            ->when($request->has('from'), fn($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->has('to'), fn($q) => $q->where('created_at', '<=', $request->to))
            ->when($request->has('type'), fn($q) => $q->where('type', $request->type))
            ->when($request->has('branch'), fn($q) => $q->where('branch_id', $request->branch))
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return response()->json([
            'data' => $movements,
            'totals' => $totals
        ]);
    }

    /**
     * Get stock valuation report.
     * Cost*qty for physical goods; strategy flag for digital.
     */
    public function stockValuation(Request $request): JsonResponse
    {
        $asOf = $request->get('as_of', now());

        $query = InventoryItem::with(['product', 'branch'])
            ->select([
                'inventory_items.product_id',
                'inventory_items.branch_id',
                'inventory_items.on_hand',
                'products.cost',
                'products.type',
                'products.pricing_strategy'
            ])
            ->join('products', 'inventory_items.product_id', '=', 'products.id');

        // Filter by branch
        if ($request->has('branch')) {
            $query->where('inventory_items.branch_id', $request->branch);
        }

        $results = $query->get()
            ->groupBy(['product_id', 'branch_id'])
            ->map(function ($items) {
                $item = $items->first();
                $totalQty = $items->sum('on_hand');

                // Calculate valuation based on product type
                if ($item->type === 'digital') {
                    $valuation = 0; // Zero-cost for digital goods
                    if ($item->pricing_strategy === 'nominal') {
                        $valuation = $totalQty * 0.01; // Nominal value
                    }
                } else {
                    $valuation = $totalQty * $item->cost;
                }

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_code' => $item->product->code,
                    'product_type' => $item->type,
                    'pricing_strategy' => $item->pricing_strategy,
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch->name,
                    'quantity' => $totalQty,
                    'unit_cost' => $item->cost,
                    'total_value' => $valuation
                ];
            })
            ->values();

        $totalValuation = $results->sum('total_value');

        return response()->json([
            'data' => $results,
            'total_valuation' => $totalValuation,
            'as_of' => $asOf
        ]);
    }

    /**
     * Get reserved backlog report.
     * Aging of reservations; identify stale holds.
     */
    public function reservedBacklog(Request $request): JsonResponse
    {
        $olderThan = $request->get('older_than', now()->subDays(30));

        $query = StockMovement::with(['product', 'branch'])
            ->select([
                'stock_movements.product_id',
                'stock_movements.branch_id',
                'stock_movements.qty',
                'stock_movements.ref',
                'stock_movements.created_at',
                'stock_movements.created_by',
                DB::raw('SUM(stock_movements.qty) as reserved_qty')
            ])
            ->where('type', 'RESERVE')
            ->where('created_at', '<=', $olderThan)
            ->groupBy(['product_id', 'branch_id', 'ref', 'created_at', 'created_by']);

        // Filter by branch
        if ($request->has('branch')) {
            $query->where('branch_id', $request->branch);
        }

        $reservations = $query->get()
            ->map(function ($reservation) {
                $daysOld = now()->diffInDays($reservation->created_at);

                return [
                    'product_id' => $reservation->product_id,
                    'product_name' => $reservation->product->name,
                    'product_code' => $reservation->product->code,
                    'branch_id' => $reservation->branch_id,
                    'branch_name' => $reservation->branch->name,
                    'reserved_qty' => $reservation->reserved_qty,
                    'ref' => $reservation->ref,
                    'created_at' => $reservation->created_at,
                    'created_by' => $reservation->created_by,
                    'days_old' => $daysOld,
                    'is_stale' => $daysOld > 60 // Consider >60 days as stale
                ];
            })
            ->sortByDesc('days_old')
            ->values();

        return response()->json([
            'data' => $reservations,
            'total_reservations' => $reservations->count(),
            'total_reserved_qty' => $reservations->sum('reserved_qty'),
            'stale_reservations' => $reservations->where('is_stale', true)->count(),
            'older_than' => $olderThan
        ]);
    }

    /**
     * Get stock movements audit report.
     * Filter by ref (receipt/supplier), movement_type, user_id.
     */
    public function auditStockMovements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'branch'])
            ->select([
                'stock_movements.id',
                'stock_movements.product_id',
                'stock_movements.branch_id',
                'stock_movements.qty',
                'stock_movements.type',
                'stock_movements.ref',
                'stock_movements.meta',
                'stock_movements.created_at',
                'stock_movements.created_by'
            ]);

        // Filter by ref
        if ($request->has('ref')) {
            $query->where('ref', $request->ref);
        }

        // Filter by user
        if ($request->has('user')) {
            $query->where('created_by', $request->user);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by branch
        if ($request->has('branch')) {
            $query->where('branch_id', $request->branch);
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($movements);
    }
}