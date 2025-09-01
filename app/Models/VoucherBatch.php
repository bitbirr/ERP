<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherBatch extends Model
{
    protected $fillable = [
        'batch_number',
        'received_at',
        'total_vouchers',
        'serial_start',
        'serial_end',
        'status',
        'metadata',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'batch_id');
    }
}
