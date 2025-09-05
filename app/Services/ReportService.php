<?php

namespace App\Services;

use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\TelebirrAgent;
use App\Models\TelebirrTransaction;
use App\Models\Branch;
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
            'total_agents' => TelebirrAgent::count(),
            'active_agents' => TelebirrAgent::where('status', 'Active')->count(),
            'total_branches' => Branch::count(),
            'total_inventory_value' => InventoryItem::join('products', 'inventory_items.product_id', '=', 'products.id')
                ->sum(DB::raw('inventory_items.on_hand * COALESCE(products.cost, 0)')),
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
        \Log::info('Executing getTopSellingProducts query');
        $query = DB::table('order_lines')
            ->join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->join('products', 'order_lines.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('sum(order_lines.qty) as total_quantity'),
                DB::raw('sum(order_lines.line_total) as total_revenue')
            )
            ->where('orders.status', 'completed')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit);
        \Log::info('Query SQL: ' . $query->toSql());
        return $query->get()->toArray();
    }

    /**
     * Get low stock items with reorder thresholds.
     */
    public function getLowStockItems(): array
    {
        return InventoryItem::with(['product', 'branch'])
            ->where('on_hand', '<=', 10)
            ->orderBy('on_hand')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product->name ?? 'Unknown Product',
                    'current_stock' => $item->on_hand,
                    'reorder_threshold' => 10, // Default threshold since not in model
                    'branch_name' => $item->branch->name ?? 'Unknown Branch',
                ];
            })
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

    /**
     * Get recent transactions (customer/product creations, Telebirr transactions).
     */
    public function getRecentTransactions(int $limit = 20): array
    {
        $transactions = [];

        // Customer creations
        $customers = Customer::select('id', 'name', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'type' => 'Customer Creation',
                    'description' => "New customer: {$customer->name}",
                    'date' => $customer->created_at->toISOString(),
                    'amount' => null,
                ];
            });

        // Product creations
        $products = Product::select('id', 'name', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'type' => 'Product Creation',
                    'description' => "New product: {$product->name}",
                    'date' => $product->created_at->toISOString(),
                    'amount' => null,
                ];
            });

        // Telebirr transactions
        $telebirrTxns = TelebirrTransaction::with(['agent'])
            ->select('id', 'tx_type', 'amount', 'created_at', 'agent_id')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'type' => 'Telebirr ' . $txn->getTypeDisplayName(),
                    'description' => $txn->agent ? "Agent: {$txn->agent->name}" : 'Telebirr Transaction',
                    'date' => $txn->created_at->toISOString(),
                    'amount' => $txn->amount,
                ];
            });

        // Combine and sort by date
        $transactions = collect([...$customers, ...$products, ...$telebirrTxns])
            ->sortByDesc('date')
            ->take($limit)
            ->values()
            ->toArray();

        return $transactions;
    }

    /**
     * Get transactions per branch.
     */
    public function getTransactionsPerBranch(): array
    {
        return DB::table('orders')
            ->join('branches', 'orders.branch_id', '=', 'branches.id')
            ->select('branches.name as branch_name', DB::raw('COUNT(orders.id) as transaction_count'))
            ->where('orders.status', 'completed')
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('transaction_count', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get top products by transaction volume.
     */
    public function getTopProductsByVolume(int $limit = 10): array
    {
        return DB::table('order_lines')
            ->join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->join('products', 'order_lines.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('SUM(order_lines.qty) as total_volume'),
                DB::raw('COUNT(order_lines.order_id) as transaction_count')
            )
            ->where('orders.status', 'completed')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_volume', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}