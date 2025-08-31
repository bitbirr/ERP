<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QueryCountGuard
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxQueries = 50): Response
    {
        // Skip for non-API routes or test environment
        if (!$request->is('api/*') || app()->environment('testing')) {
            return $next($request);
        }

        $initialQueryCount = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;

        // Enable query logging if not already enabled
        $wasLoggingEnabled = DB::logging();
        if (!$wasLoggingEnabled) {
            DB::enableQueryLog();
        }

        $response = $next($request);

        $finalQueryCount = count(DB::getQueryLog());
        $executedQueries = $finalQueryCount - $initialQueryCount;

        // Log query count for monitoring
        Log::info('Query count for request', [
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'query_count' => $executedQueries,
            'max_allowed' => $maxQueries,
            'user_id' => auth()->id(),
        ]);

        // Check if query count exceeds threshold
        if ($executedQueries > $maxQueries) {
            Log::warning('N+1 query guard triggered', [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'query_count' => $executedQueries,
                'max_allowed' => $maxQueries,
                'user_id' => auth()->id(),
                'queries' => array_slice(DB::getQueryLog(), -$executedQueries),
            ]);

            // In production, you might want to return an error response
            // For now, we'll just log the issue
            $response->headers->set('X-Query-Count', (string) $executedQueries);
            $response->headers->set('X-Query-Count-Exceeded', 'true');
        } else {
            $response->headers->set('X-Query-Count', (string) $executedQueries);
        }

        // Restore original logging state
        if (!$wasLoggingEnabled) {
            DB::disableQueryLog();
        }

        return $response;
    }
}