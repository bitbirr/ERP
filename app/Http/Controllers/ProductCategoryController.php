<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Validator;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of product categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::query();

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $categories = $query->orderBy('name')->paginate(50);

        return response()->json($categories);
    }

    /**
     * Store a newly created product category.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:product_categories,name',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = ProductCategory::create($request->all());

        return response()->json($category, 201);
    }

    /**
     * Display the specified product category.
     */
    public function show(ProductCategory $category): JsonResponse
    {
        return response()->json($category->load(['products']));
    }

    /**
     * Update the specified product category.
     */
    public function update(Request $request, ProductCategory $category): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_categories')->ignore($category->id)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $category->update($request->all());

        return response()->json($category);
    }

    /**
     * Remove the specified product category.
     */
    public function destroy(ProductCategory $category): JsonResponse
    {
        // Check if category has products
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with existing products'
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Product category deleted successfully']);
    }
}
