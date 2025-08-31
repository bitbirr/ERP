<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GlRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxRequests = 10, int $decayMinutes = 1): Response
    {
        $userId = auth()->id();
        if (!$userId) {
            return $next($request);
        }

        $key = "gl_rate_limit:{$userId}:{$request->method()}:{$request->path()}";

        // Get current request count
        $requests = Cache::get($key, 0);

        // Check if limit exceeded
        if ($requests >= $maxRequests) {
            Log::warning('GL rate limit exceeded', [
                'user_id' => $userId,
                'method' => $request->method(),
                'path' => $request->path(),
                'requests' => $requests,
                'max_requests' => $maxRequests,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'error' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => Cache::get("{$key}:reset_time", now()->addMinutes($decayMinutes)->timestamp),
            ], 429)->header('Retry-After', $decayMinutes * 60);
        }

        // Increment request count
        Cache::put($key, $requests + 1, now()->addMinutes($decayMinutes));

        // Store reset time for client reference
        Cache::put("{$key}:reset_time", now()->addMinutes($decayMinutes)->timestamp, now()->addMinutes($decayMinutes));

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', (string) $maxRequests);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $maxRequests - $requests - 1));
        $response->headers->set('X-RateLimit-Reset', (string) Cache::get("{$key}:reset_time", now()->addMinutes($decayMinutes)->timestamp));

        return $response;
    }
}