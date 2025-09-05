<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Models\Capability;
use App\Models\UserRoleAssignment;
use App\Models\StockMovement;
use App\Models\BankAccount;
use App\Models\GlAccount;
use App\Models\Transaction; // if exists
use App\Observers\ModelAuditObserver;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        User::observe(ModelAuditObserver::class);
        Role::observe(ModelAuditObserver::class);
        Capability::observe(ModelAuditObserver::class);
        UserRoleAssignment::observe(ModelAuditObserver::class);
        StockMovement::observe(ModelAuditObserver::class);
        BankAccount::observe(ModelAuditObserver::class);
        GlAccount::observe(ModelAuditObserver::class);
        \App\Models\UserPolicy::observe(ModelAuditObserver::class);
        if (class_exists(Transaction::class)) {
            Transaction::observe(ModelAuditObserver::class);
        }
    }
}
