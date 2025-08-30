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
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
            ],
            'balance' => [
                'debit' => $totals->total_debit ?? 0,
                'credit' => $totals->total_credit ?? 0,
                'net' => $netBalance,
                'transaction_count' => $totals->transaction_count ?? 0,
            ],
            'period' => [
                'from' => $request->date_from,
                'to' => $request->date_to,
            ],
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
