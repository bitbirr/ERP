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
        $query = BankAccount::with(['branch', 'customer', 'glAccount']);

        // Advanced Filtering
        if ($request->has('branch_id') && $request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('account_type') && $request->account_type) {
            $query->where('account_type', $request->account_type);
        }
        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        if ($request->has('gl_number') && $request->gl_number) {
            $query->whereHas('glAccount', function ($q) use ($request) {
                $q->where('code', 'like', '%' . $request->gl_number . '%');
            });
        }
        if ($request->has('name') && $request->name) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->has('balance_min')) {
            $query->where('balance', '>=', $request->balance_min);
        }
        if ($request->has('balance_max')) {
            $query->where('balance', '<=', $request->balance_max);
        }
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }
        if ($request->has('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->updated_from);
        }
        if ($request->has('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->updated_to);
        }

        // Sorting
        $sortBy = $request->get('sort', 'account_number');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $accounts = $query->paginate($perPage);

        // Add summary data
        $summary = [
            'total_accounts' => BankAccount::count(),
            'active_accounts' => BankAccount::where('is_active', true)->count(),
            'total_balance' => BankAccount::sum('balance'),
            'recent_transactions' => BankAccount::with(['transactions' => function ($q) {
                $q->latest()->limit(5);
            }])->get()->pluck('transactions')->flatten()->take(5)
        ];

        return response()->json([
            'data' => $accounts,
            'summary' => $summary
        ]);
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
