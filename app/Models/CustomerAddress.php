<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'type',
        'region',
        'zone',
        'woreda',
        'kebele',
        'city',
        'street_address',
        'postal_code',
        'latitude',
        'longitude',
        'is_primary',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the customer that owns the address.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the full address as a formatted string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street_address,
            $this->kebele,
            $this->woreda,
            $this->zone,
            $this->region,
            $this->city,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Scope for primary addresses.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for addresses by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for addresses by region.
     */
    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }
}