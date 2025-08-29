<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = ['amount','tx_type','channel','meta'];

    protected $casts = [
        'meta' => 'array',
    ];
}
