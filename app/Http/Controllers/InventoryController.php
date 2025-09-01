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
                    'message' => 'Bulk reserve partially failed',
                    'successful' => $results,
                    'failed' => $errors
                ], 422);
            }

            return response()->json([
                'message' => 'Bulk reserve completed successfully',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}