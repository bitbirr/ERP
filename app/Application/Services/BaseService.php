<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\DB;

use App\Domain\Audit\AuditLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseService
{
    protected AuditLogger $auditLogger;

    public function __construct()
    {
        $this->auditLogger = app(AuditLogger::class);
    }

    /**
     * Run a database transaction with error handling and audit logging.
     */
    protected function transaction(callable $callback, int $attempts = 1)
    {
        try {
            return DB::transaction($callback, $attempts);
        } catch (Throwable $e) {
            $this->auditLogger->log(
                'db.transaction_failed',
                null,
                null,
                null,
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            Log::error('Transaction failed', ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Dispatch an event with error handling and audit logging.
     */
    protected function dispatchEvent(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            $this->auditLogger->log(
                'event.dispatch_failed',
                null,
                null,
                null,
                [
                    'event' => get_class($event),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]
            );
            Log::error('Event dispatch failed', ['event' => get_class($event), 'exception' => $e]);
            throw $e;
        }
    }
}
