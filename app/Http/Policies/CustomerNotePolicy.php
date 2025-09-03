<?php

namespace App\Http\Policies;

use App\Models\CustomerNote;
use App\Models\User;

class CustomerNotePolicy
{
    /**
     * Determine whether the user can view any customer notes.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can view the customer note.
     */
    public function view(User $user, CustomerNote $customerNote): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can create customer notes.
     */
    public function create(User $user): bool
    {
        return $user->hasCapability('customer.note.manage');
    }

    /**
     * Determine whether the user can update the customer note.
     */
    public function update(User $user, CustomerNote $customerNote): bool
    {
        return $user->hasCapability('customer.note.manage');
    }

    /**
     * Determine whether the user can delete the customer note.
     */
    public function delete(User $user, CustomerNote $customerNote): bool
    {
        return $user->hasCapability('customer.note.manage');
    }
}