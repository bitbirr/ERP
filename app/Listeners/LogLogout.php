<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Domain\Audit\AuditLogger;

class LogLogout
{
    public function handle(Logout $event): void
    {
        app(AuditLogger::class)->log(
            'auth.logout',
            $event->user,
            null,
            null,
            [
                'guard' => $event->guard,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        );
    }
}