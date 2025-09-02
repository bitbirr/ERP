<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Http\Resources\CustomerContactResource;

class CustomerContactController extends Controller
{
    /**
     * Display a listing of customer contacts.
     */
    public function index(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewAny', CustomerContact::class);

        return response()->json([
            'data' => CustomerContactResource::collection($customer->contacts)
        ]);
    }

    /**
     * Store a newly created customer contact.
     */
    public function store(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('create', CustomerContact::class);

        $validated = $request->validate([
            'type' => 'required|string|in:phone,email,website',
            'value' => 'required|string',
            'is_primary' => 'boolean',
            'label' => 'nullable|string'
        ]);

        $contact = $customer->contacts()->create($validated);

        return new CustomerContactResource($contact);
    }

    /**
     * Display the specified customer contact.
     */
    public function show(Request $request, Customer $customer, CustomerContact $contact): CustomerContactResource
    {
        $this->authorize('view', $contact);

        return new CustomerContactResource($contact);
    }

    /**
     * Update the specified customer contact.
     */
    public function update(Request $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:phone,email,website',
            'value' => 'sometimes|required|string',
            'is_primary' => 'boolean',
            'label' => 'nullable|string'
        ]);

        $contact->update($validated);

        return new CustomerContactResource($contact);
    }

    /**
     * Remove the specified customer contact.
     */
    public function destroy(Request $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json(['message' => 'Customer contact deleted successfully']);
    }
}