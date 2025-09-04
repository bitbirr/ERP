<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Display a listing of orders
     */
    public function index(Request $request): OrderCollection
    {
        $query = Order::with(['customer', 'branch', 'creator']);

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

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return new OrderCollection($orders);
    }

    /**
     * Store a newly created order
     */
    public function store(StoreOrderRequest $request): OrderResource
    {
        $orderData = $request->only(['branch_id', 'customer_id', 'currency', 'notes']);
        $orderData['created_by'] = $request->user()->id;
        $lineItems = $request->input('line_items');

        $order = $this->orderService->createOrder($orderData, $lineItems);

        return new OrderResource($order->load(['lines.product', 'customer', 'branch', 'creator']));
    }

    /**
     * Display the specified order
     */
    public function show(Order $order): OrderResource
    {
        $order->load(['lines.product', 'customer', 'branch', 'creator', 'approver']);

        return new OrderResource($order);
    }

    /**
     * Update the specified order
     */
    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        // Only allow updates for pending orders
        if ($order->status !== 'pending') {
            abort(422, 'Only pending orders can be updated');
        }

        $orderData = $request->only(['customer_id', 'currency', 'notes']);
        $lineItems = $request->input('line_items');

        $order = $this->orderService->updateOrder($order, $orderData, $lineItems);

        return new OrderResource($order->load(['lines.product', 'customer', 'branch', 'creator']));
    }

    /**
     * Approve the specified order
     */
    public function approve(Request $request, Order $order): OrderResource
    {
        if ($order->status !== 'pending') {
            abort(422, 'Only pending orders can be approved');
        }

        $order = $this->orderService->approveOrder($order, $request->user()->id);

        return new OrderResource($order->load(['lines.product', 'customer', 'branch', 'creator', 'approver']));
    }

    /**
     * Cancel the specified order
     */
    public function cancel(Request $request, Order $order): OrderResource
    {
        if (!in_array($order->status, ['pending', 'approved'])) {
            abort(422, 'Order cannot be cancelled');
        }

        $order = $this->orderService->cancelOrder($order, $request->user()->id);

        return new OrderResource($order->load(['lines.product', 'customer', 'branch', 'creator']));
    }

    /**
     * Remove the specified order
     */
    public function destroy(Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json(['error' => 'Only pending orders can be deleted'], 422);
        }

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }
}