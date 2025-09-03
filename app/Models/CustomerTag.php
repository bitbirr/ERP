<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTag extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'name',
        'color',
    ];

    /**
     * Get the customer that owns the tag.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope for tags by name.
     */
    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Get the display color, defaulting to a standard color if not set.
     */
    public function getDisplayColorAttribute(): string
    {
        return $this->color ?? '#6b7280'; // Default gray color
    }
}