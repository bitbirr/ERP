<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class InventoryItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'inventory_items';

    protected $fillable = [
        'product_id',
        'branch_id',
        'on_hand',
        'reserved',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Optional: Accessor for available (if not using generated column)
    public function getAvailableAttribute()
    {
        // If 'available' column exists, this will be overridden by Eloquent
        return $this->on_hand - $this->reserved;
    }
}