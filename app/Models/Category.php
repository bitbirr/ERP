<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'customer_categories';

    protected $fillable = [
        'name',
        'description',
        'customer_count',
    ];

    protected $casts = [
        'customer_count' => 'integer',
    ];

    /**
     * Validation rules for the model.
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:customer_categories,name',
            'description' => 'nullable|string|max:1000',
            'customer_count' => 'integer|min:0',
        ];
    }

    /**
     * Get the customers for the category.
     * Note: This assumes customers table has category_id foreign key.
     * If not implemented yet, this will be added later.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'category_id');
    }

    /**
     * Scope for categories with customers.
     */
    public function scopeWithCustomers($query)
    {
        return $query->where('customer_count', '>', 0);
    }

    /**
     * Scope for categories without customers.
     */
    public function scopeWithoutCustomers($query)
    {
        return $query->where('customer_count', 0);
    }
}
