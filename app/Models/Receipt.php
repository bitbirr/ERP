<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Receipt extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'branch_id',
        'number',
        'status',
        'customer_id',
        'currency',
        'subtotal',
        'tax_total',
        'discount_total',
        'grand_total',
        'paid_total',
        'payment_method',
        'meta',
        'posted_at',
        'voided_at',
        'refunded_at',
        'created_by',
        'posted_by',
        'voided_by',
        'refunded_by',
    ];

    public function lines()
    {
        return $this->hasMany(ReceiptLine::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}