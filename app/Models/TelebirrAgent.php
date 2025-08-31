<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelebirrAgent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'short_code',
        'phone',
        'location',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the transactions for this agent
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TelebirrTransaction::class, 'agent_id');
    }

    /**
     * Scope for active agents
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Check if agent is active
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    /**
     * Get agent's outstanding balance
     */
    public function getOutstandingBalance(): float
    {
        // Calculate balance from GL subledger or transactions
        // This would typically query the GL system for balance in 1300 account
        // with subledger dimension for this agent

        $issued = $this->transactions()
            ->whereIn('tx_type', ['ISSUE', 'LOAN'])
            ->where('status', 'Posted')
            ->sum('amount');

        $repaid = $this->transactions()
            ->where('tx_type', 'REPAY')
            ->where('status', 'Posted')
            ->sum('amount');

        return $issued - $repaid;
    }

    /**
     * Get agent's transaction history
     */
    public function getTransactionHistory($limit = 50)
    {
        return $this->transactions()
            ->with(['glJournal', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
