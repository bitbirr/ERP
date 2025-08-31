<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'external_number',
        'account_number',
        'gl_account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the GL account for this bank account
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }

    /**
     * Get the transactions for this bank account
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TelebirrTransaction::class, 'bank_account_id');
    }

    /**
     * Scope for active bank accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if bank account is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get display name for the bank account
     */
    public function getDisplayName(): string
    {
        return $this->name . ' (' . $this->external_number . ')';
    }

    /**
     * Get bank name from GL account
     */
    public function getBankName(): string
    {
        return $this->glAccount?->name ?? 'Unknown Bank';
    }

    /**
     * Get transaction summary for this bank account
     */
    public function getTransactionSummary(): array
    {
        $transactions = $this->transactions()->posted()->get();

        return [
            'total_topup' => $transactions->where('tx_type', 'TOPUP')->sum('amount'),
            'total_repay' => $transactions->where('tx_type', 'REPAY')->sum('amount'),
            'net_flow' => $transactions->where('tx_type', 'TOPUP')->sum('amount') -
                         $transactions->where('tx_type', 'REPAY')->sum('amount'),
            'transaction_count' => $transactions->count(),
        ];
    }
}
