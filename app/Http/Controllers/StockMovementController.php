<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Branch;
use Illuminate\Support\Facades\Validator;

class StockMovementController extends Controller
{
    /**
     * Display a listing of stock movements.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'branch_id' => 'nullable|exists:branches,id',
            'type' => 'nullable|in:OPENING,RECEIVE,RESERVE,UNRESERVE,ISSUE,TRANSFER,ADJUST',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'ref' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = StockMovement::with(['product', 'branch']);

        // Apply filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('ref')) {
            $query->where('ref', $request->ref);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($movements);
    }

    /**
     * Display the specified stock movement.
     */
    public function show(StockMovement $stockMovement): JsonResponse
    {
        return response()->json($stockMovement->load(['product', 'branch']));
    }

    /**
     * Get stock movement summary report.
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'product_id' => 'nullable|exists:products,id',
            'branch_id' => 'nullable|exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = StockMovement::query();

        // Apply filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $summary = $query->selectRaw('
                type,
                COUNT(*) as count,
                SUM(qty) as total_quantity,
                AVG(qty) as average_quantity,
                MIN(qty) as min_quantity,
                MAX(qty) as max_quantity
            ')
            ->groupBy('type')
            ->orderBy('type')
            ->get();

        $totalMovements = $query->count();
        $totalQuantity = $query->sum('qty');

        return response()->json([
            'summary' => $summary,
            'totals' => [
                'total_movements' => $totalMovements,
                'total_quantity' => $totalQuantity
            ],
            'filters' => $request->only(['start_date', 'end_date', 'product_id', 'branch_id'])
        ]);
    }

    /**
     * Get stock movements by product.
     */
    public function byProduct(Product $product, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'branch_id' => 'nullable|exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = StockMovement::where('product_id', $product->id)
            ->with(['branch']);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'product' => $product,
            'movements' => $movements
        ]);
    }

    /**
     * Get stock movements by branch.
     */
    public function byBranch(Branch $branch, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'product_id' => 'nullable|exists:products,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = StockMovement::where('branch_id', $branch->id)
            ->with(['product']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'branch' => $branch,
            'movements' => $movements
        ]);
    }
}