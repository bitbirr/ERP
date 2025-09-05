<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Increase max execution time to prevent timeouts during heavy operations
        if (!app()->runningInConsole()) {
            set_time_limit(300); // 5 minutes for web requests
        }

        Log::info('AppServiceProvider boot start', ['timestamp' => now()]);

        // Define Gates for RBAC capabilities
        Gate::define('telebirr.view', function ($user) {
            return $user->hasCapability('telebirr.view');
        });

        Gate::define('telebirr.post', function ($user) {
            return $user->hasCapability('telebirr.post');
        });

        Gate::define('telebirr.void', function ($user) {
            return $user->hasCapability('telebirr.void');
        });

        Gate::define('telebirr.manage', function ($user) {
            return $user->hasCapability('telebirr.manage');
        });

        // Add more capability gates as needed
        Gate::define('tx.view', function ($user) {
            return $user->hasCapability('tx.view');
        });

        Gate::define('receipts.create', function ($user) {
            return $user->hasCapability('receipts.create');
        });

        Gate::define('receipts.void', function ($user) {
            return $user->hasCapability('receipts.void');
        });

        Gate::define('gl.view', function ($user) {
            return $user->hasCapability('gl.view');
        });

        Gate::define('gl.create', function ($user) {
            return $user->hasCapability('gl.create');
        });

        Gate::define('gl.post', function ($user) {
            return $user->hasCapability('gl.post');
        });

        Gate::define('gl.reverse', function ($user) {
            return $user->hasCapability('gl.reverse');
        });

        Log::info('AppServiceProvider boot end', ['timestamp' => now()]);
    }
}
