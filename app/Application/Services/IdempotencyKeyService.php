<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\Cache;

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
     * Validate the idempotency key.
     *
     * @param string $key
     * @return bool
     */
    public function validate($key)
    {
        if (Cache::has($key)) {
            return false; // Key already used
        }

        // Store the key to prevent reuse
        Cache::put($key, true, now()->addMinutes(5));

        return true;
    }
}