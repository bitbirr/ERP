<?php

namespace App\Services;

use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get system summary metrics.
     */
    public function getSummary(): array
    {
        return [
            'total_users' => User::count(),
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('is_active', true)->count(),
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('status', 'completed')->sum('grand_total'),
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'total_inventory_value' => InventoryItem::sum(DB::raw('on_hand * cost')),
            'low_stock_items' => InventoryItem::where('on_hand', '<=', 10)->count(),
            'total_stock_movements' => StockMovement::count(),
        ];
    }

    /**
     * Get orders summary by status.
     */
    public function getOrdersSummary(): array
    {
        return Order::select('status', DB::raw('count(*) as count'), DB::raw('sum(grand_total) as total'))
            ->groupBy('status')
            ->get()
            ->toArray();
    }

    /**
     * Get revenue over time (last 30 days).
     */
    public function getRevenueOverTime(): array
    {
        return Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('sum(grand_total) as revenue')
            )
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get top selling products.
     */
    public function getTopSellingProducts(int $limit = 10): array
    {
        return DB::table('order_lines')
            ->join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->join('products', 'order_lines.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('sum(order_lines.quantity) as total_quantity'),
                DB::raw('sum(order_lines.total) as total_revenue')
            )
            ->where('orders.status', 'completed')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get low stock items.
     */
    public function getLowStockItems(): array
    {
        return InventoryItem::with(['product', 'branch'])
            ->where('on_hand', '<=', 10)
            ->orderBy('on_hand')
            ->get()
            ->toArray();
    }

    /**
     * Get recent orders.
     */
    public function getRecentOrders(int $limit = 10): array
    {
        return Order::with(['customer', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}