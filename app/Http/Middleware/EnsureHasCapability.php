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

        $hasCapability = $user && $user->hasCapability($capability, $branch);

        // Audit RBAC check
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
            return response()->json(['message' => "Forbidden: missing capability {$capability}"], 403);
        }

        return $next($request);
    }
}
