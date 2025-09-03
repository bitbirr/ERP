<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryService
{
    /**
     * Create a new category with validation.
     */
    public function createCategory(array $data): Category
    {
        $this->validateCategoryData($data);

        DB::beginTransaction();
        try {
            $category = Category::create($data);

            Log::info('Category created', [
                'category_id' => $category->id,
                'name' => $category->name,
                'user_id' => auth()->id()
            ]);

            DB::commit();
            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create category', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing category with validation.
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $this->validateCategoryData($data, $category->id);

        DB::beginTransaction();
        try {
            $category->update($data);

            Log::info('Category updated', [
                'category_id' => $category->id,
                'name' => $category->name,
                'user_id' => auth()->id()
            ]);

            DB::commit();
            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update category', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Delete a category with dependency check.
     */
    public function deleteCategory(Category $category): bool
    {
        // Check if category has customers
        if ($category->customer_count > 0) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete category with assigned customers. Please reassign customers first.'
            ]);
        }

        DB::beginTransaction();
        try {
            $categoryId = $category->id;
            $categoryName = $category->name;

            $category->delete();

            Log::info('Category deleted', [
                'category_id' => $categoryId,
                'name' => $categoryName,
                'user_id' => auth()->id()
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete category', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get category by ID with relationships.
     */
    public function getCategoryById(string $id): Category
    {
        $category = Category::with('customers')->find($id);

        if (!$category) {
            throw new ModelNotFoundException('Category not found');
        }

        return $category;
    }

    /**
     * Get all categories with optional filtering and pagination.
     */
    public function getCategories(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Category::query();

        // Search filter
        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Sort options
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        if (in_array($sortBy, ['name', 'created_at', 'customer_count'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('name');
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Assign customer to category and update counts.
     */
    public function assignCustomerToCategory(string $customerId, string $categoryId): Customer
    {
        $customer = Customer::findOrFail($customerId);
        $category = Category::findOrFail($categoryId);

        DB::beginTransaction();
        try {
            // If customer was in another category, decrement that count
            if ($customer->category_id) {
                $oldCategory = Category::find($customer->category_id);
                if ($oldCategory) {
                    $oldCategory->decrement('customer_count');
                }
            }

            $customer->category_id = $categoryId;
            $customer->save();

            $category->increment('customer_count');

            Log::info('Customer assigned to category', [
                'customer_id' => $customerId,
                'category_id' => $categoryId,
                'user_id' => auth()->id()
            ]);

            DB::commit();
            return $customer->load('category');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign customer to category', [
                'customer_id' => $customerId,
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Remove customer from category and update counts.
     */
    public function removeCustomerFromCategory(string $customerId): Customer
    {
        $customer = Customer::findOrFail($customerId);

        if (!$customer->category_id) {
            throw ValidationException::withMessages([
                'customer' => 'Customer is not assigned to any category'
            ]);
        }

        DB::beginTransaction();
        try {
            $categoryId = $customer->category_id;
            $category = Category::find($categoryId);

            $customer->category_id = null;
            $customer->save();

            if ($category) {
                $category->decrement('customer_count');
            }

            Log::info('Customer removed from category', [
                'customer_id' => $customerId,
                'category_id' => $categoryId,
                'user_id' => auth()->id()
            ]);

            DB::commit();
            return $customer;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove customer from category', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get category statistics.
     */
    public function getCategoryStats(): array
    {
        return [
            'total_categories' => Category::count(),
            'categories_with_customers' => Category::where('customer_count', '>', 0)->count(),
            'categories_without_customers' => Category::where('customer_count', 0)->count(),
            'total_customers_categorized' => Category::sum('customer_count'),
        ];
    }

    /**
     * Validate category data.
     */
    private function validateCategoryData(array $data, ?string $excludeId = null): void
    {
        $rules = Category::rules();

        if ($excludeId) {
            $rules['name'] = 'required|string|max:255|unique:customer_categories,name,' . $excludeId;
        }

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}