<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'discount_percentage',
        'max_discount_amount',
        'min_purchase_amount',
        'points_per_etb',
        'points_required_for_discount',
        'valid_days',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'discount_percentage' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'points_per_etb' => 'integer',
        'points_required_for_discount' => 'integer',
        'valid_days' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(LoyaltyDiscount::class);
    }
}