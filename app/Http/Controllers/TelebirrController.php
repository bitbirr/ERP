<?php

namespace App\Http\Controllers;

use App\Models\TelebirrAgent;
use App\Models\TelebirrTransaction;
use App\Services\TelebirrService;
use App\Http\Requests\Telebirr\CreateAgentRequest;
use App\Http\Requests\Telebirr\UpdateAgentRequest;
use App\Http\Requests\Telebirr\PostTopupRequest;
use App\Http\Requests\Telebirr\PostIssueRequest;
use App\Http\Requests\Telebirr\PostRepayRequest;
use App\Http\Requests\Telebirr\PostLoanRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Exception;

class TelebirrController extends Controller
{
    private TelebirrService $telebirrService;

    public function __construct(TelebirrService $telebirrService)
    {
        $this->telebirrService = $telebirrService;
    }

    // ===== AGENT MANAGEMENT =====

    /**
     * List all agents
     */
    public function agents(Request $request): JsonResponse
    {
        Gate::authorize('telebirr.view');

        $query = TelebirrAgent::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $perPage = $request->get('per_page', 50);
        $agents = $query->paginate($perPage);

        return response()->json([
            'data' => $agents->items(),
            'meta' => [
                'total' => $agents->total(),
                'per_page' => $agents->perPage(),
                'current_page' => $agents->currentPage(),
                'last_page' => $agents->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific agent
     */
    public function agent(TelebirrAgent $agent): JsonResponse
    {
        Gate::authorize('telebirr.view');

        return response()->json([
            'data' => $agent->load(['transactions' => function ($query) {
                $query->latest()->limit(10);
            }]),
        ]);
    }

    /**
     * Create new agent
     */
    public function createAgent(CreateAgentRequest $request): JsonResponse
    {
        $agent = TelebirrAgent::create($request->validated());

        return response()->json([
            'message' => 'Agent created successfully',
            'data' => $agent,
        ], 201);
    }

    /**
     * Update agent
     */
    public function updateAgent(UpdateAgentRequest $request, TelebirrAgent $agent): JsonResponse
    {
        $agent->update($request->validated());

        return response()->json([
            'message' => 'Agent updated successfully',
            'data' => $agent,
        ]);
    }

    // ===== TRANSACTION MANAGEMENT =====

    /**
     * List transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        Gate::authorize('telebirr.view');

        $query = TelebirrTransaction::with(['agent', 'bankAccount', 'glJournal', 'createdBy']);

        // Apply filters
        if ($request->has('tx_type')) {
            $query->where('tx_type', $request->tx_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('external_ref', 'like', "%{$search}%")
                  ->orWhere('remarks', 'like', "%{$search}%")
                  ->orWhereHas('agent', function ($agentQuery) use ($search) {
                      $agentQuery->where('short_code', 'like', "%{$search}%");
                  });
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 50);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific transaction
     */
    public function transaction(TelebirrTransaction $transaction): JsonResponse
    {
        Gate::authorize('telebirr.view');

        return response()->json([
            'data' => $transaction->load(['agent', 'bankAccount', 'glJournal.lines', 'createdBy', 'approvedBy']),
        ]);
    }

    /**
     * Post TOPUP transaction
     */
    public function postTopup(PostTopupRequest $request): JsonResponse
    {
        try {
            $transaction = $this->telebirrService->postTopup($request->validated());

            return response()->json([
                'message' => 'Topup transaction posted successfully',
                'data' => $transaction->load(['agent', 'bankAccount', 'glJournal']),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to post topup transaction',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Post ISSUE transaction
     */
    public function postIssue(PostIssueRequest $request): JsonResponse
    {
        try {
            $transaction = $this->telebirrService->postIssue($request->validated());

            return response()->json([
                'message' => 'Issue transaction posted successfully',
                'data' => $transaction->load(['agent', 'glJournal']),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to post issue transaction',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Post REPAY transaction
     */
    public function postRepay(PostRepayRequest $request): JsonResponse
    {
        try {
            $transaction = $this->telebirrService->postRepay($request->validated());

            return response()->json([
                'message' => 'Repay transaction posted successfully',
                'data' => $transaction->load(['agent', 'bankAccount', 'glJournal']),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to post repay transaction',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Post LOAN transaction
     */
    public function postLoan(PostLoanRequest $request): JsonResponse
    {
        try {
            $transaction = $this->telebirrService->postLoan($request->validated());

            return response()->json([
                'message' => 'Loan transaction posted successfully',
                'data' => $transaction->load(['agent', 'glJournal']),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to post loan transaction',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Void transaction
     */
    public function voidTransaction(TelebirrTransaction $transaction): JsonResponse
    {
        Gate::authorize('telebirr.void');

        if (!$transaction->canBeVoided()) {
            return response()->json([
                'message' => 'Transaction cannot be voided',
            ], 400);
        }

        try {
            DB::transaction(function () use ($transaction) {
                // Mark transaction as voided
                $transaction->update(['status' => 'Voided']);

                // Reverse the GL journal
                $this->telebirrService->reverseJournal($transaction->gl_journal_id);
            });

            return response()->json([
                'message' => 'Transaction voided successfully',
                'data' => $transaction->load(['agent', 'bankAccount', 'glJournal']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to void transaction',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // ===== RECONCILIATION =====

    /**
     * Get reconciliation data
     */
    public function reconciliation(Request $request): JsonResponse
    {
        Gate::authorize('telebirr.view');

        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        // Get transactions for period
        $transactions = TelebirrTransaction::with(['agent', 'bankAccount'])
            ->whereBetween('created_at', [$request->date_from, $request->date_to])
            ->where('status', 'Posted')
            ->get();

        // Calculate reconciliation data
        $reconciliation = [
            'period' => [
                'from' => $request->date_from,
                'to' => $request->date_to,
            ],
            'summary' => [
                'total_transactions' => $transactions->count(),
                'total_amount' => $transactions->sum('amount'),
                'by_type' => $transactions->groupBy('tx_type')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                    ];
                }),
            ],
            'transactions' => $transactions,
        ];

        return response()->json($reconciliation);
    }

    // ===== REPORTING =====

    /**
     * Get agent balance report
     */
    public function agentBalances(Request $request): JsonResponse
    {
        Gate::authorize('telebirr.view');

        $agents = TelebirrAgent::active()->get();

        $balances = $agents->map(function ($agent) {
            return [
                'agent' => $agent,
                'outstanding_balance' => $agent->getOutstandingBalance(),
                'last_transaction' => $agent->transactions()->latest()->first(),
            ];
        });

        return response()->json([
            'data' => $balances,
            'generated_at' => now(),
        ]);
    }

    /**
     * Get transaction summary report
     */
    public function transactionSummary(Request $request): JsonResponse
    {
        Gate::authorize('telebirr.view');

        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $query = TelebirrTransaction::whereBetween('created_at', [$request->date_from, $request->date_to])
            ->where('status', 'Posted');

        $summary = [
            'period' => [
                'from' => $request->date_from,
                'to' => $request->date_to,
            ],
            'totals' => [
                'count' => $query->count(),
                'amount' => $query->sum('amount'),
            ],
            'by_type' => $query->selectRaw('tx_type, COUNT(*) as count, SUM(amount) as amount')
                ->groupBy('tx_type')
                ->get(),
            'by_agent' => $query->with('agent')
                ->selectRaw('agent_id, COUNT(*) as count, SUM(amount) as amount')
                ->groupBy('agent_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'agent' => $item->agent,
                        'count' => $item->count,
                        'amount' => $item->amount,
                    ];
                }),
        ];

        return response()->json($summary);
    }
}