<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->paginate(50);

        return response()->json($products);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|uuid|exists:product_categories,id',
            'code' => 'required|string|max:50|unique:products,code',
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(['YIMULU', 'SERVICE', 'OTHER'])],
            'uom' => 'required|string|max:10',
            'price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'pricing_strategy' => ['nullable', Rule::in(['FIXED', 'PERCENTAGE', 'MARGIN'])],
            'description' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create($request->all());

        return response()->json($product, 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load(['inventoryItems', 'receiptLines', 'category']));
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|uuid|exists:product_categories,id',
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('products')->ignore($product->id)],
            'name' => 'sometimes|required|string|max:255',
            'type' => ['sometimes', 'required', Rule::in(['YIMULU', 'SERVICE', 'OTHER'])],
            'uom' => 'sometimes|required|string|max:10',
            'price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'pricing_strategy' => ['nullable', Rule::in(['FIXED', 'PERCENTAGE', 'MARGIN'])],
            'description' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if type is being changed and if product has stock
        if ($request->has('type') && $request->type !== $product->type) {
            if ($product->inventoryItems()->where('on_hand', '>', 0)->exists()) {
                return response()->json([
                    'message' => 'Cannot change product type when stock exists'
                ], 422);
            }
        }

        $product->update($request->all());

        return response()->json($product);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check if product has inventory or receipts
        if ($product->inventoryItems()->exists() || $product->receiptLines()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing inventory or receipts'
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}