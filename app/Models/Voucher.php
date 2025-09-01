<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'batch_id',
        'serial_number',
        'status',
        'reserved_for_order_id',
        'issued_at',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(VoucherBatch::class, 'batch_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(VoucherReservation::class);
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(VoucherIssuance::class);
    }
}
