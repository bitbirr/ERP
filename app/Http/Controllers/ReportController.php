<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ReportService;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get system summary report.
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->reportService->getSummary();
        return response()->json($summary);
    }

    /**
     * Get dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = [
            'summary' => $this->reportService->getSummary(),
            'orders_summary' => $this->reportService->getOrdersSummary(),
            'revenue_over_time' => $this->reportService->getRevenueOverTime(),
            'top_selling_products' => $this->reportService->getTopSellingProducts(),
            'low_stock_items' => $this->reportService->getLowStockItems(),
            'recent_orders' => $this->reportService->getRecentOrders(),
        ];

        return response()->json($data);
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

    /**
     * Get orders summary.
     */
    public function ordersSummary(Request $request): JsonResponse
    {
        $summary = $this->reportService->getOrdersSummary();
        return response()->json($summary);
    }

    /**
     * Get revenue over time.
     */
    public function revenueOverTime(Request $request): JsonResponse
    {
        $data = $this->reportService->getRevenueOverTime();
        return response()->json($data);
    }

    /**
     * Get top selling products.
     */
    public function topSellingProducts(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $data = $this->reportService->getTopSellingProducts($limit);
        return response()->json($data);
    }

    /**
     * Get low stock items.
     */
    public function lowStockItems(Request $request): JsonResponse
    {
        $data = $this->reportService->getLowStockItems();
        return response()->json($data);
    }

    /**
     * Get recent orders.
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $data = $this->reportService->getRecentOrders($limit);
        return response()->json($data);
    }
}