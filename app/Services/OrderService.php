<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Support\Collection;

class OrderService
{
    /**
     * Create a new order with lines
     */
    public function createOrder(array $orderData, array $lineItems): Order
    {
        $orderData = $this->calculateOrderTotals($lineItems, $orderData);
        $orderData['order_number'] = $this->generateOrderNumber($orderData['branch_id']);
        $orderData['status'] = 'pending';

        $order = Order::create($orderData);

        foreach ($lineItems as $lineItem) {
            $lineItem = $this->calculateLineTotals($lineItem);
            $lineItem['order_id'] = $order->id;
            OrderLine::create($lineItem);
        }

        return $order->load('lines');
    }

    /**
     * Update an existing order
     */
    public function updateOrder(Order $order, array $orderData, array $lineItems = null): Order
    {
        if ($lineItems !== null) {
            $orderData = $this->calculateOrderTotals($lineItems, $orderData);
            $order->lines()->delete(); // Remove existing lines

            foreach ($lineItems as $lineItem) {
                $lineItem = $this->calculateLineTotals($lineItem);
                $lineItem['order_id'] = $order->id;
                OrderLine::create($lineItem);
            }
        }

        $order->update($orderData);
        return $order->fresh()->load('lines');
    }

    /**
     * Approve an order
     */
    public function approveOrder(Order $order, int $approvedBy): Order
    {
        $order->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Order $order, int $cancelledBy): Order
    {
        $order->update([
            'status' => 'cancelled',
            'cancelled_by' => $cancelledBy,
            'cancelled_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * Calculate totals for an order
     */
    protected function calculateOrderTotals(array $lineItems, array $orderData): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        $discountTotal = 0;

        foreach ($lineItems as $lineItem) {
            $subtotal += $lineItem['qty'] * $lineItem['price'];
            $taxTotal += $lineItem['tax_amount'] ?? 0;
            $discountTotal += $lineItem['discount'] ?? 0;
        }

        $orderData['subtotal'] = $subtotal;
        $orderData['tax_total'] = $taxTotal;
        $orderData['discount_total'] = $discountTotal;
        $orderData['grand_total'] = $subtotal + $taxTotal - $discountTotal;

        return $orderData;
    }

    /**
     * Calculate totals for a line item
     */
    protected function calculateLineTotals(array $lineItem): array
    {
        $lineTotal = ($lineItem['qty'] * $lineItem['price']) + ($lineItem['tax_amount'] ?? 0) - ($lineItem['discount'] ?? 0);
        $lineItem['line_total'] = $lineTotal;
        return $lineItem;
    }

    /**
     * Generate a unique order number for the branch
     */
    protected function generateOrderNumber(string $branchId): string
    {
        $date = now()->format('Ymd');
        $lastOrder = Order::where('branch_id', $branchId)
            ->where('order_number', 'like', "{$date}%")
            ->orderBy('order_number', 'desc')
            ->first();

        $sequence = $lastOrder ? intval(substr($lastOrder->order_number, 8)) + 1 : 1;
        return $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}