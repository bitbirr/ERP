<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'tax_id',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the contacts for the customer.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /**
     * Get the addresses for the customer.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Get the notes for the customer.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * Get the tags for the customer.
     */
    public function tags(): HasMany
    {
        return $this->hasMany(CustomerTag::class);
    }

    /**
     * Get the segments for the customer.
     */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(CustomerSegment::class, 'customer_segment_assignments');
    }

    /**
     * Get the interactions for the customer.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class);
    }

    /**
     * Get the primary contact.
     */
    public function primaryContact()
    {
        return $this->contacts()->where('is_primary', true)->first();
    }

    /**
     * Get the primary address.
     */
    public function primaryAddress()
    {
        return $this->addresses()->where('is_primary', true)->first();
    }

    /**
     * Scope for active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for individual customers.
     */
    public function scopeIndividuals($query)
    {
        return $query->where('type', 'individual');
    }

    /**
     * Scope for organization customers.
     */
    public function scopeOrganizations($query)
    {
        return $query->where('type', 'organization');
    }
}