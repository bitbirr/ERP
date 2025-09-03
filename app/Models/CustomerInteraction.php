<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'created_by',
        'type',
        'direction',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the interaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who created the interaction.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for interactions by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for interactions by direction.
     */
    public function scopeByDirection($query, $direction)
    {
        return $query->where('direction', $direction);
    }

    /**
     * Scope for interactions by creator.
     */
    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope for interactions within date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Get the display name for the type.
     */
    public function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'call' => 'Phone Call',
            'email' => 'Email',
            'meeting' => 'Meeting',
            'note' => 'Note',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get the display name for the direction.
     */
    public function getDirectionDisplayAttribute(): ?string
    {
        return match($this->direction) {
            'inbound' => 'Inbound',
            'outbound' => 'Outbound',
            default => null,
        };
    }
}