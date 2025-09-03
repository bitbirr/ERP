<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Http\Resources\CustomerSegmentResource;
use App\Http\Resources\CustomerResource;

class CustomerSegmentController extends Controller
{
    /**
     * Display a listing of customer segments.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSegment::class);

        $segments = CustomerSegment::withCount('customers')->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CustomerSegmentResource::collection($segments),
            'meta' => [
                'total' => $segments->total(),
                'per_page' => $segments->perPage(),
                'current_page' => $segments->currentPage(),
                'last_page' => $segments->lastPage()
            ]
        ]);
    }

    /**
     * Store a newly created customer segment.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerSegment::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'criteria' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        $segment = CustomerSegment::create($validated);

        return new CustomerSegmentResource($segment);
    }

    /**
     * Display the specified customer segment.
     */
    public function show(Request $request, CustomerSegment $segment): CustomerSegmentResource
    {
        $this->authorize('view', $segment);

        return new CustomerSegmentResource($segment->loadCount('customers'));
    }

    /**
     * Update the specified customer segment.
     */
    public function update(Request $request, CustomerSegment $segment): JsonResponse
    {
        $this->authorize('update', $segment);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'criteria' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        $segment->update($validated);

        return new CustomerSegmentResource($segment);
    }

    /**
     * Remove the specified customer segment.
     */
    public function destroy(Request $request, CustomerSegment $segment): JsonResponse
    {
        $this->authorize('delete', $segment);

        $segment->delete();

        return response()->json(['message' => 'Customer segment deleted successfully']);
    }

    /**
     * Get customers in a segment.
     */
    public function members(Request $request, CustomerSegment $segment): JsonResponse
    {
        $this->authorize('viewMembers', $segment);

        $customers = $segment->customers()->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CustomerResource::collection($customers),
            'meta' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage()
            ]
        ]);
    }

    /**
     * Preview segment criteria.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('preview', CustomerSegment::class);

        $criteria = $request->validate([
            'criteria' => 'required|array'
        ]);

        // This would implement segment preview logic
        // For now, return empty result
        return response()->json([
            'data' => [],
            'count' => 0
        ]);
    }
}