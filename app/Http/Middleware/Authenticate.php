<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API routes, don't redirect, let it throw AuthenticationException
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        return route('login');
    }
}