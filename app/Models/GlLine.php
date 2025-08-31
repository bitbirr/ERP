<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GlLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'journal_id',
        'line_no',
        'account_id',
        'branch_id',
        'cost_center_id',
        'project_id',
        'customer_id',
        'supplier_id',
        'item_id',
        'memo',
        'debit',
        'credit',
        'meta',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'meta' => 'array',
    ];

    // Relationships
    public function journal(): BelongsTo
    {
        return $this->belongsTo(GlJournal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    // Dimension relationships (would need corresponding models)
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    // Scopes
    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeDebit($query)
    {
        return $query->where('debit', '>', 0);
    }

    public function scopeCredit($query)
    {
        return $query->where('credit', '>', 0);
    }

    // Helper methods
    public function isDebit(): bool
    {
        return $this->debit > 0 && $this->credit == 0;
    }

    public function isCredit(): bool
    {
        return $this->credit > 0 && $this->debit == 0;
    }

    public function getAmount(): float
    {
        return max($this->debit, $this->credit);
    }

    public function getSignedAmount(): float
    {
        if ($this->isDebit()) {
            return $this->debit;
        } elseif ($this->isCredit()) {
            return -$this->credit;
        }
        return 0;
    }

    public function isValid(): bool
    {
        // Check that only one side has a value
        $debitCount = $this->debit > 0 ? 1 : 0;
        $creditCount = $this->credit > 0 ? 1 : 0;

        return ($debitCount + $creditCount) === 1;
    }

    public function hasAmount(): bool
    {
        return $this->debit > 0 || $this->credit > 0;
    }

    public function getType(): string
    {
        if ($this->isDebit()) {
            return 'debit';
        } elseif ($this->isCredit()) {
            return 'credit';
        }
        return 'zero';
    }

    /**
     * Get the account code through the relationship
     */
    public function getAccountCodeAttribute(): ?string
    {
        return $this->account?->code;
    }
}
