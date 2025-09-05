<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerAddress;
use App\Models\CustomerNote;
use App\Models\CustomerTag;
use App\Models\CustomerSegment;
use App\Http\Policies\CategoryPolicy;
use App\Http\Policies\CustomerPolicy;
use App\Http\Policies\CustomerContactPolicy;
use App\Http\Policies\CustomerAddressPolicy;
use App\Http\Policies\CustomerNotePolicy;
use App\Http\Policies\CustomerTagPolicy;
use App\Http\Policies\CustomerSegmentPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Category::class => CategoryPolicy::class,
        Customer::class => CustomerPolicy::class,
        CustomerContact::class => CustomerContactPolicy::class,
        CustomerAddress::class => CustomerAddressPolicy::class,
        CustomerNote::class => CustomerNotePolicy::class,
        CustomerTag::class => CustomerTagPolicy::class,
        CustomerSegment::class => CustomerSegmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}