<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\AuditLog;
use App\Services\CategoryService;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryCollection;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request, CategoryService $categoryService)
    {
        $this->authorize('viewAny', Category::class);

        $filters = $request->only(['q', 'sort_by', 'sort_direction', 'per_page']);
        $categories = $categoryService->getCategories($filters);

        return new CategoryCollection($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request, CategoryService $categoryService)
    {
        $this->authorize('create', Category::class);

        $category = $categoryService->createCategory($request->validated());

        // Log audit
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_ip' => $request->ip(),
            'actor_user_agent' => $request->userAgent(),
            'action' => 'create',
            'subject_type' => 'category',
            'subject_id' => $category->id,
            'changes_new' => $category->toArray(),
            'context' => ['source' => 'api'],
        ]);

        return new CategoryResource($category);
    }

    /**
     * Display the specified category.
     */
    public function show(Request $request, Category $category)
    {
        $this->authorize('view', $category);

        return new CategoryResource($category->load('customers'));
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category, CategoryService $categoryService)
    {
        $this->authorize('update', $category);

        $oldData = $category->toArray();
        $category = $categoryService->updateCategory($category, $request->validated());

        // Log audit
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_ip' => $request->ip(),
            'actor_user_agent' => $request->userAgent(),
            'action' => 'update',
            'subject_type' => 'category',
            'subject_id' => $category->id,
            'changes_old' => $oldData,
            'changes_new' => $category->toArray(),
            'context' => ['source' => 'api'],
        ]);

        return new CategoryResource($category);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Request $request, Category $category, CategoryService $categoryService)
    {
        $this->authorize('delete', $category);

        $oldData = $category->toArray();

        $categoryService->deleteCategory($category);

        // Log audit
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_ip' => $request->ip(),
            'actor_user_agent' => $request->userAgent(),
            'action' => 'delete',
            'subject_type' => 'category',
            'subject_id' => $category->id,
            'changes_old' => $oldData,
            'context' => ['source' => 'api'],
        ]);

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * Assign customer to category.
     */
    public function assignCustomer(Request $request, CategoryService $categoryService)
    {
        $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'category_id' => 'required|uuid|exists:customer_categories,id',
        ]);

        $this->authorize('update', Category::findOrFail($request->category_id));

        $customer = $categoryService->assignCustomerToCategory(
            $request->customer_id,
            $request->category_id
        );

        return response()->json([
            'message' => 'Customer assigned to category successfully',
            'customer' => $customer
        ]);
    }

    /**
     * Remove customer from category.
     */
    public function removeCustomer(Request $request, CategoryService $categoryService)
    {
        $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
        ]);

        $customer = $categoryService->removeCustomerFromCategory($request->customer_id);

        return response()->json([
            'message' => 'Customer removed from category successfully',
            'customer' => $customer
        ]);
    }

    /**
     * Get category statistics.
     */
    public function stats(CategoryService $categoryService)
    {
        $this->authorize('viewAny', Category::class);

        $stats = $categoryService->getCategoryStats();

        return response()->json($stats);
    }
}