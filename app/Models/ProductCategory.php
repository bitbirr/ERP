<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'product_categories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'product_count',
        'is_active',
    ];

    protected $casts = [
        'product_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Validation rules for the model.
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:product_categories,name',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the products for the category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Scope for active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for categories with products.
     */
    public function scopeWithProducts($query)
    {
        return $query->where('product_count', '>', 0);
    }

    /**
     * Scope for categories without products.
     */
    public function scopeWithoutProducts($query)
    {
        return $query->where('product_count', 0);
    }
}
