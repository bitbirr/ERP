<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'code', 'address', 'phone', 'manager', 'location', 'status'];

    /**
     * Get the bank accounts for this branch
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'branch_id');
    }
}
