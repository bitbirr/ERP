<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        // Define Gates for RBAC capabilities
        Gate::define('telebirr.view', function ($user) {
            return $user->hasCapability('telebirr.view');
        });

        Gate::define('telebirr.void', function ($user) {
            return $user->hasCapability('telebirr.void');
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
    }
}
