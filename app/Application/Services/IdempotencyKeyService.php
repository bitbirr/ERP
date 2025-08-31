<?php

namespace App\Application\Services;

use App\Models\TelebirrTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IdempotencyKeyService
{
    /**
     * Generate a unique idempotency key.
     *
     * @return string
     */
    public function generate()
    {
        return uniqid('idempotency_', true);
    }

    /**
     * Validate the idempotency key for a specific route and payload.
     *
     * @param string $key
     * @param string $route
     * @param array $payload
     * @return bool
     */
    public function validate($key, $route = null, $payload = null)
    {
        // Check if key already exists in database
        $existing = TelebirrTransaction::where('idempotency_key', $key)->first();

        if ($existing) {
            Log::info('Idempotency key already used', [
                'key' => $key,
                'existing_transaction_id' => $existing->id,
                'route' => $route
            ]);
            return false;
        }

        // Check cache for short-term duplicate prevention
        $cacheKey = "idempotency:{$key}";
        if (Cache::has($cacheKey)) {
            Log::warning('Idempotency key recently used (cache)', [
                'key' => $key,
                'route' => $route
            ]);
            return false;
        }

        // Store in cache to prevent race conditions
        Cache::put($cacheKey, [
            'route' => $route,
            'payload_hash' => $payload ? md5(serialize($payload)) : null,
            'timestamp' => now()->toISOString()
        ], now()->addMinutes(10)); // Keep for 10 minutes

        return true;
    }

    /**
     * Check if idempotency key exists and return the transaction if found.
     *
     * @param string $key
     * @return TelebirrTransaction|null
     */
    public function getExistingTransaction($key)
    {
        return TelebirrTransaction::where('idempotency_key', $key)->first();
    }

    /**
     * Clear idempotency key from cache (useful for testing or cleanup).
     *
     * @param string $key
     * @return void
     */
    public function clearCache($key)
    {
        $cacheKey = "idempotency:{$key}";
        Cache::forget($cacheKey);
    }

    /**
     * Validate idempotency key with payload comparison.
     * This ensures the same key with different payload is rejected.
     *
     * @param string $key
     * @param array $payload
     * @return bool
     */
    public function validateWithPayload($key, array $payload)
    {
        $cacheKey = "idempotency:{$key}";
        $cached = Cache::get($cacheKey);

        if ($cached) {
            $payloadHash = md5(serialize($payload));
            if ($cached['payload_hash'] !== $payloadHash) {
                Log::warning('Idempotency key used with different payload', [
                    'key' => $key,
                    'cached_hash' => $cached['payload_hash'],
                    'new_hash' => $payloadHash
                ]);
                return false;
            }
        }

        return true;
    }
}