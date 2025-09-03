<?php

namespace App\Http\Controllers;

use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoyaltyController extends Controller
{
    protected $loyaltyService;

    public function __construct(LoyaltyService $loyaltyService)
    {
        $this->loyaltyService = $loyaltyService;
    }

    /**
     * Get top customers by purchase amount
     */
    public function getTopCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'integer|min:1|max:365'
        ]);

        $days = $request->get('days', 90);
        $topCustomers = $this->loyaltyService->getTopCustomers($days);

        return response()->json([
            'data' => $topCustomers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'total_purchases' => $customer->total_purchases,
                ];
            }),
            'meta' => [
                'count' => $topCustomers->count(),
                'period_days' => $days,
            ]
        ]);
    }

    /**
     * Generate loyalty discount for a customer
     */
    public function generateDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'basket_total' => 'required|numeric|min:0',
        ]);

        $customerId = $request->customer_id;
        $basketTotal = (float) $request->basket_total;

        $discount = $this->loyaltyService->generateDiscount($customerId, $basketTotal);

        if (!$discount) {
            throw ValidationException::withMessages([
                'basket_total' => ['Basket total does not meet minimum purchase requirement for loyalty discount.']
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $discount->id,
                'customer_id' => $discount->customer_id,
                'discount_amount' => $discount->discount_amount,
                'discount_percentage' => $discount->discount_percentage,
                'original_total' => $discount->original_total,
                'final_total' => $discount->final_total,
                'expires_at' => $discount->expires_at,
            ]
        ], 201);
    }

    /**
     * Get customer's loyalty points balance
     */
    public function getCustomerPoints(string $customerId): JsonResponse
    {
        $points = $this->loyaltyService->getCustomerPoints($customerId);

        return response()->json([
            'customer_id' => $customerId,
            'points_balance' => $points,
        ]);
    }

    /**
     * Get customer's unused loyalty discounts
     */
    public function getCustomerDiscounts(string $customerId): JsonResponse
    {
        $discounts = $this->loyaltyService->getCustomerDiscounts($customerId);

        return response()->json([
            'data' => $discounts->map(function ($discount) {
                return [
                    'id' => $discount->id,
                    'discount_amount' => $discount->discount_amount,
                    'discount_percentage' => $discount->discount_percentage,
                    'original_total' => $discount->original_total,
                    'final_total' => $discount->final_total,
                    'expires_at' => $discount->expires_at,
                    'is_used' => $discount->is_used,
                ];
            }),
            'meta' => [
                'count' => $discounts->count(),
            ]
        ]);
    }
}