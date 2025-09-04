<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Branch::query();

        // Filtering
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->has('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        // Sorting
        $sortBy = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $branches = $query->paginate($perPage);

        return response()->json($branches);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:branches',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'manager' => 'required|string|max:255',
        ]);

        $branch = Branch::create($validated);

        return response()->json($branch, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        return response()->json($branch);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:branches,code,' . $id,
            'address' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'manager' => 'sometimes|required|string|max:255',
        ]);

        $branch->update($validated);

        return response()->json($branch);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        // Check if branch has accounts
        if ($branch->bankAccounts()->exists()) {
            // Soft delete
            $branch->delete();
            return response()->json(['message' => 'Branch soft deleted because it has associated accounts']);
        } else {
            // Hard delete
            $branch->forceDelete();
            return response()->json(['message' => 'Branch permanently deleted']);
        }
    }

    /**
     * Fetch all Branch UUIDs from external endpoint
     */
    public function fetchUuids(): JsonResponse
    {
        try {
            // Assuming external endpoint is /api/external/branches/uuids
            $response = Http::get('https://external-api.example.com/api/branches/uuids');

            if ($response->successful()) {
                $uuids = $response->json();
                // Optionally, validate or enrich branch data here
                return response()->json($uuids);
            } else {
                return response()->json(['error' => 'Failed to fetch UUIDs from external service'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'External service unavailable'], 503);
        }
    }
}
