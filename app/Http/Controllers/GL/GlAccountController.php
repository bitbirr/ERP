<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use App\Models\GlAccount;
use App\Http\Resources\GL\GlAccountResource;
use App\Http\Resources\GL\GlAccountCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GlAccountController extends Controller
{
    /**
     * Display a listing of accounts.
     */
    public function index(Request $request): GlAccountCollection
    {
        Gate::authorize('gl.view');

        $query = GlAccount::with(['parent', 'children', 'branch']);

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->has('is_postable')) {
            $query->where('is_postable', $request->boolean('is_postable'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Order by hierarchy
        $query->orderBy('level')->orderBy('code');

        // Pagination
        $perPage = $request->get('per_page', 50);
        $accounts = $query->paginate($perPage);

        return new GlAccountCollection($accounts);
    }

    /**
     * Display the specified account.
     */
    public function show(GlAccount $account): GlAccountResource
    {
        Gate::authorize('gl.view');

        return new GlAccountResource(
            $account->load(['parent', 'children', 'branch'])
        );
    }

    /**
     * Get account tree structure.
     */
    public function tree(Request $request): JsonResponse
    {
        Gate::authorize('gl.view');

        $query = GlAccount::with(['children', 'branch']);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $rootAccounts = $query->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $rootAccounts->map(function ($account) {
                return $this->buildAccountTree($account);
            }),
        ]);
    }

    /**
     * Get account balance.
     */
    public function balance(GlAccount $account, Request $request): JsonResponse
    {
        Gate::authorize('gl.view');

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'branch_id' => 'nullable|uuid',
        ]);

        $query = $account->lines()
            ->join('gl_journals', 'gl_lines.journal_id', '=', 'gl_journals.id')
            ->where('gl_journals.status', 'POSTED');

        if ($request->has('date_from')) {
            $query->where('gl_journals.journal_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('gl_journals.journal_date', '<=', $request->date_to);
        }

        if ($request->has('branch_id')) {
            $query->where('gl_lines.branch_id', $request->branch_id);
        }

        $totals = $query->selectRaw('
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            COUNT(*) as transaction_count
        ')->first();

        $netBalance = ($totals->total_debit ?? 0) - ($totals->total_credit ?? 0);

        return response()->json([
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'balance' => $netBalance,
            'debit_total' => $totals->total_debit ?? 0,
            'credit_total' => $totals->total_credit ?? 0,
            'as_of_date' => now()->toISOString(),
        ]);
    }

    /**
     * Store a newly created account.
     */
    public function store(Request $request): GlAccountResource
    {
        Gate::authorize('gl.create');

        $validated = $request->validate([
            'code' => 'required|string|unique:gl_accounts,code|max:10',
            'name' => 'required|string|max:255',
            'type' => 'required|in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE',
            'normal_balance' => 'required|in:DEBIT,CREDIT',
            'parent_id' => 'nullable|uuid|exists:gl_accounts,id',
            'is_postable' => 'boolean',
            'branch_id' => 'nullable|uuid|exists:branches,id',
        ]);

        // Auto-generate 4-digit code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateAccountCode();
        }

        // Set level based on parent
        if ($validated['parent_id']) {
            $parent = GlAccount::find($validated['parent_id']);
            $validated['level'] = $parent->level + 1;
        } else {
            $validated['level'] = 1;
        }

        $account = GlAccount::create($validated);

        return new GlAccountResource($account->load(['parent', 'children', 'branch']));
    }

    /**
     * Update the specified account.
     */
    public function update(Request $request, GlAccount $account): GlAccountResource
    {
        Gate::authorize('gl.update');

        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:gl_accounts,code,' . $account->id . '|max:10',
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE',
            'normal_balance' => 'sometimes|required|in:DEBIT,CREDIT',
            'parent_id' => 'nullable|uuid|exists:gl_accounts,id',
            'is_postable' => 'boolean',
            'status' => 'sometimes|required|in:ACTIVE,ARCHIVED',
            'branch_id' => 'nullable|uuid|exists:branches,id',
        ]);

        // Prevent circular references
        if ($validated['parent_id'] && $this->wouldCreateCircularReference($account, $validated['parent_id'])) {
            return response()->json(['error' => 'Circular reference detected'], 422);
        }

        // Update level if parent changed
        if (isset($validated['parent_id'])) {
            if ($validated['parent_id']) {
                $parent = GlAccount::find($validated['parent_id']);
                $validated['level'] = $parent->level + 1;
            } else {
                $validated['level'] = 1;
            }
        }

        $account->update($validated);

        return new GlAccountResource($account->load(['parent', 'children', 'branch']));
    }

    /**
     * Remove the specified account.
     */
    public function destroy(GlAccount $account): JsonResponse
    {
        Gate::authorize('gl.delete');

        // Check if account has children
        if ($account->children()->exists()) {
            return response()->json([
                'error' => 'Cannot delete account with child accounts. Please delete child accounts first.'
            ], 422);
        }

        // Check if account has journal entries
        if ($account->lines()->exists()) {
            // Soft delete by archiving
            $account->update(['status' => 'ARCHIVED']);
            return response()->json(['message' => 'Account archived successfully']);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /**
     * Generate a unique 4-digit account code.
     */
    private function generateAccountCode(): string
    {
        do {
            $code = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (GlAccount::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check for circular references in account hierarchy.
     */
    private function wouldCreateCircularReference(GlAccount $account, string $parentId): bool
    {
        $current = GlAccount::find($parentId);
        while ($current) {
            if ($current->id === $account->id) {
                return true;
            }
            $current = $current->parent;
        }
        return false;
    }

    /**
     * Get account summary metrics.
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('gl.view');

        $query = GlAccount::query();

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $totalAccounts = $query->count();
        $activeAccounts = (clone $query)->where('status', 'ACTIVE')->count();
        $postableAccounts = (clone $query)->where('is_postable', true)->count();

        $accountsByType = (clone $query)->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return response()->json([
            'total_accounts' => $totalAccounts,
            'active_accounts' => $activeAccounts,
            'postable_accounts' => $postableAccounts,
            'accounts_by_type' => $accountsByType,
        ]);
    }

    /**
     * Build account tree structure recursively.
     */
    private function buildAccountTree(GlAccount $account): array
    {
        $children = $account->children->map(function ($child) {
            return $this->buildAccountTree($child);
        });

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'level' => $account->level,
            'is_postable' => $account->is_postable,
            'status' => $account->status,
            'children' => $children,
            'has_children' => $children->isNotEmpty(),
        ];
    }
}
