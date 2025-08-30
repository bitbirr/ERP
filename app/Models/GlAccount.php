<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GlAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'type',
        'normal_balance',
        'parent_id',
        'level',
        'is_postable',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'is_postable' => 'boolean',
        'level' => 'integer',
    ];

    // Self-referencing relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(GlAccount::class, 'parent_id');
    }

    // Journal lines relationship
    public function lines(): HasMany
    {
        return $this->hasMany(GlLine::class, 'account_id');
    }

    // Branch relationship
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopePostable($query)
    {
        return $query->where('is_postable', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByNormalBalance($query, $balance)
    {
        return $query->where('normal_balance', $balance);
    }

    // Helper methods
    public function isAsset(): bool
    {
        return $this->type === 'ASSET';
    }

    public function isLiability(): bool
    {
        return $this->type === 'LIABILITY';
    }

    public function isEquity(): bool
    {
        return $this->type === 'EQUITY';
    }

    public function isRevenue(): bool
    {
        return $this->type === 'REVENUE';
    }

    public function isExpense(): bool
    {
        return $this->type === 'EXPENSE';
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'DEBIT';
    }

    public function isCreditNormal(): bool
    {
        return $this->normal_balance === 'CREDIT';
    }

    public function getFullCode(): string
    {
        $codes = [];
        $account = $this;

        while ($account) {
            array_unshift($codes, $account->code);
            $account = $account->parent;
        }

        return implode('-', $codes);
    }
}
