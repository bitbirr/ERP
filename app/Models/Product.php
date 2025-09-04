<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'products';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'category_id',
        'code',
        'name',
        'type',
        'uom',
        'price',
        'cost',
        'discount_percent',
        'pricing_strategy',
        'is_active',
        'meta',
        'description',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function receiptLines()
    {
        return $this->hasMany(ReceiptLine::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}