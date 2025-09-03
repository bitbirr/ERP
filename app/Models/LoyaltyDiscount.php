<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'loyalty_program_id',
        'customer_id',
        'receipt_id',
        'discount_amount',
        'discount_percentage',
        'original_total',
        'final_total',
        'is_used',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'original_total' => 'decimal:2',
        'final_total' => 'decimal:2',
        'is_used' => 'boolean',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function scopeUnused($query)
    {
        return $query->where('is_used', false);
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
}