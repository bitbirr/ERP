<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get system summary report.
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = [
            'total_products' => Product::count(),
            'total_inventory_items' => InventoryItem::sum('on_hand'),
            'total_stock_movements' => StockMovement::count(),
            'low_stock_items' => InventoryItem::where('on_hand', '<=', 10)->count(),
        ];

        return response()->json($summary);
    }

    /**
     * Get inventory report.
     */
    public function inventory(Request $request): JsonResponse
    {
        $query = InventoryItem::with(['product', 'branch']);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $inventory = $query->orderBy('product_id')->get();

        return response()->json($inventory);
    }

    /**
     * Get products report.
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $products = $query->with(['inventoryItems'])->get();

        return response()->json($products);
    }
}