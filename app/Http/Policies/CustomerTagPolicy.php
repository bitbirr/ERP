<?php

namespace App\Http\Policies;

use App\Models\CustomerTag;
use App\Models\User;

class CustomerTagPolicy
{
    /**
     * Determine whether the user can view any customer tags.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can view the customer tag.
     */
    public function view(User $user, CustomerTag $customerTag): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can create customer tags.
     */
    public function create(User $user): bool
    {
        return $user->hasCapability('customer.tag.manage');
    }

    /**
     * Determine whether the user can update the customer tag.
     */
    public function update(User $user, CustomerTag $customerTag): bool
    {
        return $user->hasCapability('customer.tag.manage');
    }

    /**
     * Determine whether the user can delete the customer tag.
     */
    public function delete(User $user, CustomerTag $customerTag): bool
    {
        return $user->hasCapability('customer.tag.manage');
    }
}