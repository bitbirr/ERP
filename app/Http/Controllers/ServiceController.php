<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Service;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\ServiceCollection;

class ServiceController extends Controller
{
    /**
     * Display a listing of services.
     */
    public function index(Request $request): ServiceCollection
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query();

        // Search
        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
        }

        // Filter by category
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $services = $query->orderBy('name')
                          ->paginate($request->get('per_page', 50));

        return new ServiceCollection($services);
    }

    /**
     * Store a newly created service.
     */
    public function store(Request $request): ServiceResource
    {
        $this->authorize('create', Service::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $service = Service::create($validated);

        return new ServiceResource($service);
    }

    /**
     * Display the specified service.
     */
    public function show(Request $request, Service $service): ServiceResource
    {
        $this->authorize('view', $service);

        return new ServiceResource($service);
    }

    /**
     * Update the specified service.
     */
    public function update(Request $request, Service $service): ServiceResource
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'price' => 'sometimes|nullable|numeric|min:0',
            'category' => 'sometimes|nullable|string|max:255',
            'is_active' => 'boolean',
            'metadata' => 'sometimes|nullable|array',
        ]);

        $service->update($validated);

        return new ServiceResource($service);
    }

    /**
     * Remove the specified service.
     */
    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return response()->json(['message' => 'Service deleted successfully']);
    }
}
