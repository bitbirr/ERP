<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class GlJournal extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'journal_no',
        'journal_date',
        'currency',
        'fx_rate',
        'source',
        'reference',
        'memo',
        'status',
        'posted_at',
        'posted_by',
        'branch_id',
        'external_ref',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'fx_rate' => 'decimal:6',
        'posted_at' => 'datetime',
    ];

    // Relationships
    public function lines(): HasMany
    {
        return $this->hasMany(GlLine::class, 'journal_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'POSTED');
    }

    public function scopeVoided($query)
    {
        return $query->where('status', 'VOIDED');
    }

    public function scopeReversed($query)
    {
        return $query->where('status', 'REVERSED');
    }

    public function scopeByPeriod($query, $startDate, $endDate = null)
    {
        $query->where('journal_date', '>=', $startDate);

        if ($endDate) {
            $query->where('journal_date', '<=', $endDate);
        }

        return $query;
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Helper methods
    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPosted(): bool
    {
        return $this->status === 'POSTED';
    }

    public function isVoided(): bool
    {
        return $this->status === 'VOIDED';
    }

    public function isReversed(): bool
    {
        return $this->status === 'REVERSED';
    }

    public function canBePosted(): bool
    {
        return $this->isDraft() && $this->validateBalance();
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function validateBalance(): bool
    {
        $totals = $this->lines()
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        return $totals && $totals->total_debit == $totals->total_credit;
    }

    public function getTotalDebit(): float
    {
        return $this->lines()->sum('debit');
    }

    public function getTotalCredit(): float
    {
        return $this->lines()->sum('credit');
    }

    public function getLineCount(): int
    {
        return $this->lines()->count();
    }

    public function hasMinimumLines(): bool
    {
        return $this->getLineCount() >= 2;
    }

    public function getNextLineNumber(): int
    {
        return $this->lines()->max('line_no') + 1;
    }
}
