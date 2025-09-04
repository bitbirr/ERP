<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BankAccount::with(['branch', 'customer']);

        // Filtering
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        // Sorting
        $sortBy = $request->get('sort', 'account_number');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $accounts = $query->paginate($perPage);

        return response()->json($accounts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'external_number' => 'required|string|max:255|unique:bank_accounts',
            'account_number' => 'required|string|max:255',
            'gl_account_id' => 'required|uuid|exists:gl_accounts,id',
            'account_type' => 'required|string|max:255',
            'balance' => 'numeric|min:0',
            'branch_id' => 'required|uuid|exists:branches,id',
            'customer_id' => 'required|uuid|exists:customers,id',
        ]);

        $account = BankAccount::create($validated);

        return response()->json($account->load(['branch', 'customer']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $account = BankAccount::with(['branch', 'customer'])->findOrFail($id);

        return response()->json($account);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $account = BankAccount::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'external_number' => 'sometimes|required|string|max:255|unique:bank_accounts,external_number,' . $id,
            'account_number' => 'sometimes|required|string|max:255',
            'gl_account_id' => 'sometimes|required|uuid|exists:gl_accounts,id',
            'account_type' => 'sometimes|required|string|max:255',
            'balance' => 'sometimes|numeric|min:0',
            'branch_id' => 'sometimes|required|uuid|exists:branches,id',
            'customer_id' => 'sometimes|required|uuid|exists:customers,id',
        ]);

        $account->update($validated);

        return response()->json($account->load(['branch', 'customer']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $account = BankAccount::findOrFail($id);

        if ($account->balance > 0) {
            return response()->json(['error' => 'Cannot delete account with non-zero balance'], 400);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
