<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use App\Domain\Audit\AuditLogger;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        app(AuditLogger::class)->log(
            'auth.failed',
            null,
            null,
            null,
            [
                'credentials' => $event->credentials,
                'user_id' => $event->user?->getKey(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        );
    }
}