<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerSegment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'criteria',
        'is_active',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the customers in this segment.
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_segment_assignments');
    }

    /**
     * Scope for active segments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the customer count for this segment.
     */
    public function getCustomerCountAttribute(): int
    {
        return $this->customers()->count();
    }

    /**
     * Apply segment criteria to a query.
     * This is a placeholder for dynamic segment logic.
     */
    public function applyCriteria($query)
    {
        if (!$this->criteria) {
            return $query;
        }

        // Example: criteria could be ['type' => 'individual', 'region' => 'Addis Ababa']
        foreach ($this->criteria as $field => $value) {
            if ($field === 'type') {
                $query->where('type', $value);
            } elseif ($field === 'region') {
                $query->whereHas('addresses', function ($q) use ($value) {
                    $q->where('region', $value)->where('is_primary', true);
                });
            }
            // Add more criteria as needed
        }

        return $query;
    }
}