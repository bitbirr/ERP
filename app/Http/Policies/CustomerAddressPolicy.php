<?php

namespace App\Http\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    /**
     * Determine whether the user can view any customer addresses.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can view the customer address.
     */
    public function view(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can create customer addresses.
     */
    public function create(User $user): bool
    {
        return $user->hasCapability('customer.update');
    }

    /**
     * Determine whether the user can update the customer address.
     */
    public function update(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->hasCapability('customer.update');
    }

    /**
     * Determine whether the user can delete the customer address.
     */
    public function delete(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->hasCapability('customer.update');
    }
}