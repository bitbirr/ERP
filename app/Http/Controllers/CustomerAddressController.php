<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Http\Resources\CustomerAddressResource;

class CustomerAddressController extends Controller
{
    /**
     * Display a listing of customer addresses.
     */
    public function index(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewAny', CustomerAddress::class);

        return response()->json([
            'data' => CustomerAddressResource::collection($customer->addresses)
        ]);
    }

    /**
     * Store a newly created customer address.
     */
    public function store(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('create', CustomerAddress::class);

        $validated = $request->validate([
            'type' => 'required|string|in:home,work,billing,shipping',
            'street_address' => 'required|string',
            'city' => 'required|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'required|string',
            'is_primary' => 'boolean',
            'label' => 'nullable|string'
        ]);

        $address = $customer->addresses()->create($validated);

        return new CustomerAddressResource($address);
    }

    /**
     * Display the specified customer address.
     */
    public function show(Request $request, Customer $customer, CustomerAddress $address): CustomerAddressResource
    {
        $this->authorize('view', $address);

        return new CustomerAddressResource($address);
    }

    /**
     * Update the specified customer address.
     */
    public function update(Request $request, Customer $customer, CustomerAddress $address): JsonResponse
    {
        $this->authorize('update', $address);

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:home,work,billing,shipping',
            'street_address' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'sometimes|required|string',
            'is_primary' => 'boolean',
            'label' => 'nullable|string'
        ]);

        $address->update($validated);

        return new CustomerAddressResource($address);
    }

    /**
     * Remove the specified customer address.
     */
    public function destroy(Request $request, Customer $customer, CustomerAddress $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return response()->json(['message' => 'Customer address deleted successfully']);
    }
}