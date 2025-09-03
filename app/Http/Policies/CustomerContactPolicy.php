<?php

namespace App\Http\Policies;

use App\Models\CustomerContact;
use App\Models\User;

class CustomerContactPolicy
{
    /**
     * Determine whether the user can view any customer contacts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can view the customer contact.
     */
    public function view(User $user, CustomerContact $customerContact): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can create customer contacts.
     */
    public function create(User $user): bool
    {
        return $user->hasCapability('customer.update');
    }

    /**
     * Determine whether the user can update the customer contact.
     */
    public function update(User $user, CustomerContact $customerContact): bool
    {
        return $user->hasCapability('customer.update');
    }

    /**
     * Determine whether the user can delete the customer contact.
     */
    public function delete(User $user, CustomerContact $customerContact): bool
    {
        return $user->hasCapability('customer.update');
    }
}