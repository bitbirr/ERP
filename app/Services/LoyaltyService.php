<?php

namespace App\Services;

use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use App\Models\LoyaltyDiscount;
use App\Models\Customer;
use App\Models\Receipt;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class LoyaltyService
{
    protected $loyaltyProgram;

    public function __construct()
    {
        // Get the default loyalty program or create one if it doesn't exist
        $this->loyaltyProgram = LoyaltyProgram::firstOrCreate(
            ['name' => 'Default Loyalty Program'],
            [
                'description' => 'Default loyalty program for customers',
                'is_active' => true,
                'discount_percentage' => 10.00,
                'max_discount_amount' => 500.00,
                'min_purchase_amount' => 100.00,
                'points_per_etb' => 1,
                'points_required_for_discount' => 100,
                'valid_days' => 30,
            ]
        );
    }

    /**
     * Get top customers by total purchase amount in the last N days
     */
    public function getTopCustomers(int $days = 90): Collection
    {
        // Get customer IDs with their total purchases first
        $customerTotals = Receipt::select('customer_id')
            ->selectRaw('SUM(grand_total) as total_purchases')
            ->where('status', 'POSTED')
            ->where('posted_at', '>=', Carbon::now()->subDays($days))
            ->groupBy('customer_id')
            ->orderByRaw('SUM(grand_total) DESC')
            ->limit(10)
            ->pluck('total_purchases', 'customer_id');

        // Get the actual customer data
        return Customer::whereIn('id', $customerTotals->keys())
            ->get()
            ->map(function ($customer) use ($customerTotals) {
                $customer->total_purchases = $customerTotals[$customer->id];
                return $customer;
            })
            ->sortByDesc('total_purchases')
            ->values();
    }

    /**
     * Generate loyalty discount for a customer
     */
    public function generateDiscount(string $customerId, float $basketTotal): ?LoyaltyDiscount
    {
        $customer = Customer::findOrFail($customerId);

        // Check if customer qualifies for discount
        if ($basketTotal < $this->loyaltyProgram->min_purchase_amount) {
            return null;
        }

        // Calculate discount amount
        $discountAmount = min(
            $basketTotal * ($this->loyaltyProgram->discount_percentage / 100),
            $this->loyaltyProgram->max_discount_amount
        );

        // Create discount record
        return LoyaltyDiscount::create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'customer_id' => $customerId,
            'discount_amount' => $discountAmount,
            'discount_percentage' => $this->loyaltyProgram->discount_percentage,
            'original_total' => $basketTotal,
            'final_total' => $basketTotal - $discountAmount,
            'expires_at' => Carbon::now()->addDays($this->loyaltyProgram->valid_days),
        ]);
    }

    /**
     * Record loyalty transaction when customer makes a purchase
     */
    public function recordPurchase(string $customerId, string $receiptId, float $amount): LoyaltyTransaction
    {
        $pointsEarned = (int) ($amount * $this->loyaltyProgram->points_per_etb);

        return LoyaltyTransaction::create([
            'loyalty_program_id' => $this->loyaltyProgram->id,
            'customer_id' => $customerId,
            'receipt_id' => $receiptId,
            'points_earned' => $pointsEarned,
            'points_used' => 0,
            'transaction_type' => 'earn',
            'description' => "Earned {$pointsEarned} points for purchase of {$amount} ETB",
            'transaction_date' => now(),
        ]);
    }

    /**
     * Get customer's current loyalty points balance
     */
    public function getCustomerPoints(string $customerId): int
    {
        return LoyaltyTransaction::where('customer_id', $customerId)
            ->sum('points_earned') - LoyaltyTransaction::where('customer_id', $customerId)
            ->sum('points_used');
    }

    /**
     * Get customer's unused discounts
     */
    public function getCustomerDiscounts(string $customerId): Collection
    {
        return LoyaltyDiscount::where('customer_id', $customerId)
            ->unused()
            ->valid()
            ->orderBy('expires_at')
            ->get();
    }
}