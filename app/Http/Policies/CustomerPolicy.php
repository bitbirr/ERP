<?php

namespace App\Http\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        return $user->hasCapability('customer.create');
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->hasCapability('customer.update');
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasCapability('customer.delete');
    }

    /**
     * Determine whether the user can restore the customer.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $user->hasCapability('customer.restore');
    }

    /**
     * Determine whether the user can permanently delete the customer.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->hasCapability('customer.delete');
    }

    /**
     * Determine whether the user can merge customers.
     */
    public function merge(User $user): bool
    {
        return $user->hasCapability('customer.merge');
    }

    /**
     * Determine whether the user can import customers.
     */
    public function import(User $user): bool
    {
        return $user->hasCapability('customer.import');
    }

    /**
     * Determine whether the user can export customers.
     */
    public function export(User $user): bool
    {
        return $user->hasCapability('customer.export');
    }

    /**
     * Determine whether the user can manage customer tags.
     */
    public function manageTags(User $user): bool
    {
        return $user->hasCapability('customer.tag.manage');
    }

    /**
     * Determine whether the user can manage customer notes.
     */
    public function manageNotes(User $user): bool
    {
        return $user->hasCapability('customer.note.manage');
    }

    /**
     * Determine whether the user can manage customer files.
     */
    public function manageFiles(User $user): bool
    {
        return $user->hasCapability('customer.file.manage');
    }

    /**
     * Determine whether the user can manage customer segments.
     */
    public function manageSegments(User $user): bool
    {
        return $user->hasCapability('customer.segment.manage');
    }

    /**
     * Determine whether the user can view customer timeline.
     */
    public function viewTimeline(User $user, Customer $customer): bool
    {
        return $user->hasCapability('customer.view');
    }
}