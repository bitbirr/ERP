<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerAddress;
use App\Models\CustomerNote;
use App\Models\CustomerTag;
use App\Models\CustomerInteraction;
use App\Models\AuditLog;
use App\Services\CustomerService;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerCollection;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request, CustomerService $customerService): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $filters = $request->only(['type', 'is_active', 'segment_id', 'region', 'q', 'tag', 'per_page']);
        $customers = $customerService->searchCustomers($filters);

        return new CustomerCollection($customers);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request, CustomerService $customerService): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $customerService->createCustomer($request->validated());

        // Log audit
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_ip' => $request->ip(),
            'actor_user_agent' => $request->userAgent(),
            'action' => 'create',
            'subject_type' => 'customer',
            'subject_id' => $customer->id,
            'changes_new' => $customer->toArray(),
            'context' => ['source' => 'api'],
        ]);

        return new CustomerResource($customer->load(['contacts', 'addresses']));
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->load([
            'contacts',
            'addresses',
            'notes.creator',
            'tags',
            'segments',
            'interactions.creator'
        ]));
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer, CustomerService $customerService): JsonResponse
    {
        $this->authorize('update', $customer);

        $oldData = $customer->toArray();
        $customer = $customerService->updateCustomer($customer, $request->validated());

        // Log audit
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_ip' => $request->ip(),
            'actor_user_agent' => $request->userAgent(),
            'action' => 'update',
            'subject_type' => 'customer',
            'subject_id' => $customer->id,
            'changes_old' => $oldData,
            'changes_new' => $customer->toArray(),
            'context' => ['source' => 'api'],
        ]);

        return new CustomerResource($customer->load(['contacts', 'addresses']));
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        // Check if customer has related data
        if ($customer->interactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete customer with existing interactions'
            ], 422);
        }

        $oldData = $customer->toArray();

        $customer->delete();

        // Log audit
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_ip' => $request->ip(),
            'actor_user_agent' => $request->userAgent(),
            'action' => 'delete',
            'subject_type' => 'customer',
            'subject_id' => $customer->id,
            'changes_old' => $oldData,
            'context' => ['source' => 'api'],
        ]);

        return response()->json(['message' => 'Customer deleted successfully']);
    }
}