<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /**
     * Get audit logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        // Apply filters
        if ($request->has('user_id')) {
            $query->where('actor_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('entity_type')) {
            $query->where('subject_type', $request->entity_type);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $logs = $query->with(['actor'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * Get specific audit log.
     */
    public function show(AuditLog $log): JsonResponse
    {
        return response()->json($log->load(['user']));
    }
}