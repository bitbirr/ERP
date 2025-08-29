<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Domain\Audit\AuditLogger;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        app(AuditLogger::class)->log(
            'auth.login',
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