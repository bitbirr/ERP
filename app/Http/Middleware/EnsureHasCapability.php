<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        $branchId = $request->header('X-Branch-Id');
        $branch = null;
        if ($branchId) {
            $branch = Branch::find($branchId);
        }

        // Debug logging
        \Log::info('RBAC Check Debug', [
            'capability' => $capability,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'branch_id' => $branchId,
            'has_user' => $user ? 'YES' : 'NO',
        ]);

        $hasCapability = $user && $user->hasCapability($capability, $branch);

        // Debug capability check
        \Log::info('Capability Check Result', [
            'capability' => $capability,
            'has_capability' => $hasCapability,
            'user_id' => $user?->id,
        ]);

        // Audit RBAC check (wrapped in try-catch to prevent HTML responses)
        try {
            app(\App\Domain\Audit\AuditLogger::class)->log(
                'rbac.check',
                $user,
                null,
                null,
                [
                    'capability' => $capability,
                    'branch_id' => $branchId,
                    'result' => $hasCapability ? 'granted' : 'denied',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );
        } catch (\Exception $e) {
            // Log the audit error but don't fail the request
            \Log::error('Audit logging failed in RBAC middleware', [
                'error' => $e->getMessage(),
                'capability' => $capability,
                'user_id' => $user?->id,
            ]);
        }

        if (! $user) {
            \Log::warning('RBAC Access Denied: Unauthenticated', [
                'capability' => $capability,
                'branch_id' => $branchId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! $hasCapability) {
            \Log::warning('RBAC Access Denied', [
                'user_id' => $user?->id,
                'capability' => $capability,
                'branch_id' => $branchId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
            return response()->json([
                'error' => 'Forbidden',
                'message' => "Missing required capability: {$capability}",
                'capability' => $capability,
                'user_id' => $user?->id
            ], 403);
        }

        // Debug: Log successful capability check
        \Log::info('RBAC Access Granted', [
            'user_id' => $user?->id,
            'capability' => $capability,
            'branch_id' => $branchId,
        ]);

        return $next($request);
    }
}
