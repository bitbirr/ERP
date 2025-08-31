<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelebirrTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'tx_type',
        'agent_id',
        'bank_account_id',
        'amount',
        'currency',
        'idempotency_key',
        'gl_journal_id',
        'status',
        'remarks',
        'external_ref',
        'created_by',
        'approved_by',
        'posted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    /**
     * Get the agent for this transaction
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(TelebirrAgent::class, 'agent_id');
    }

    /**
     * Get the bank account for this transaction
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * Get the GL journal for this transaction
     */
    public function glJournal(): BelongsTo
    {
        return $this->belongsTo(GlJournal::class, 'gl_journal_id');
    }

    /**
     * Get the user who created this transaction
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this transaction
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for posted transactions
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'Posted');
    }

    /**
     * Scope for voided transactions
     */
    public function scopeVoided($query)
    {
        return $query->where('status', 'Voided');
    }

    /**
     * Check if transaction is posted
     */
    public function isPosted(): bool
    {
        return $this->status === 'Posted';
    }

    /**
     * Check if transaction is voided
     */
    public function isVoided(): bool
    {
        return $this->status === 'Voided';
    }

    /**
     * Check if transaction can be voided
     */
    public function canBeVoided(): bool
    {
        return $this->isPosted() && !$this->isVoided();
    }

    /**
     * Get transaction type display name
     */
    public function getTypeDisplayName(): string
    {
        return match($this->tx_type) {
            'ISSUE' => 'Issue E-float',
            'REPAY' => 'Repayment',
            'LOAN' => 'Loan E-float',
            'TOPUP' => 'Topup',
            default => $this->tx_type,
        };
    }

    /**
     * Get transaction status color for UI
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'Posted' => 'green',
            'Voided' => 'red',
            'Draft' => 'yellow',
            default => 'gray',
        };
    }
}
