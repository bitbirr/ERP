<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use App\Models\GlJournal;
use App\Services\GL\GlService;
use App\Http\Requests\GL\StoreGlJournalRequest;
use App\Http\Requests\GL\PostGlJournalRequest;
use App\Http\Requests\GL\ReverseGlJournalRequest;
use App\Http\Resources\GL\GlJournalResource;
use App\Http\Resources\GL\GlJournalCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GlJournalController extends Controller
{
    public function __construct(
        private GlService $glService
    ) {}

    /**
     * Display a listing of journals.
     */
    public function index(Request $request): GlJournalCollection
    {
        Gate::authorize('gl.view');

        $query = GlJournal::with(['lines.account', 'postedBy', 'branch']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('date_from')) {
            $query->where('journal_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('journal_date', '<=', $request->date_to);
        }

        if ($request->has('account_id')) {
            $query->whereHas('lines', function ($q) use ($request) {
                $q->where('account_id', $request->account_id);
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $journals = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return new GlJournalCollection($journals);
    }

    /**
     * Store a newly created journal.
     */
    public function store(StoreGlJournalRequest $request): JsonResponse
    {
        $journal = $this->glService->createJournal($request->validated());

        return response()->json([
            'message' => 'Journal created successfully',
            'data' => new GlJournalResource($journal->load(['lines.account', 'branch'])),
        ], 201);
    }

    /**
     * Display the specified journal.
     */
    public function show(GlJournal $journal): GlJournalResource
    {
        Gate::authorize('gl.view');

        return new GlJournalResource(
            $journal->load(['lines.account', 'postedBy', 'branch'])
        );
    }

    /**
     * Post a journal.
     */
    public function post(PostGlJournalRequest $request, GlJournal $journal): JsonResponse
    {
        try {
            $idempotencyKey = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');

            $this->glService->post($journal, $idempotencyKey);

            return response()->json([
                'message' => 'Journal posted successfully',
                'data' => new GlJournalResource($journal->fresh(['lines.account', 'postedBy', 'branch'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to post journal',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reverse a journal.
     */
    public function reverse(ReverseGlJournalRequest $request, GlJournal $journal): JsonResponse
    {
        try {
            $reversingJournal = $this->glService->reverse($journal, $request->reason);

            return response()->json([
                'message' => 'Journal reversed successfully',
                'data' => [
                    'original' => new GlJournalResource($journal->fresh(['lines.account', 'postedBy', 'branch'])),
                    'reversal' => new GlJournalResource($reversingJournal->load(['lines.account', 'branch'])),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reverse journal',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Void a journal.
     */
    public function void(Request $request, GlJournal $journal): JsonResponse
    {
        Gate::authorize('gl.reverse');

        $request->validate([
            'reason' => 'required|string|max:500|min:5',
        ]);

        try {
            $this->glService->void($journal, $request->reason);

            return response()->json([
                'message' => 'Journal voided successfully',
                'data' => new GlJournalResource($journal->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to void journal',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Validate a draft journal.
     */
    public function validateDraft(GlJournal $journal): JsonResponse
    {
        Gate::authorize('gl.view');

        $errors = $this->glService->validateDraft($journal);

        if (empty($errors)) {
            return response()->json([
                'valid' => true,
                'message' => 'Journal is valid and ready for posting',
            ]);
        }

        return response()->json([
            'valid' => false,
            'errors' => $errors,
        ], 422);
    }
}
