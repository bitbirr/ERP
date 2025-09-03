<?php

namespace App\Http\Policies;

use App\Models\CustomerSegment;
use App\Models\User;

class CustomerSegmentPolicy
{
    /**
     * Determine whether the user can view any customer segments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can view the customer segment.
     */
    public function view(User $user, CustomerSegment $customerSegment): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can create customer segments.
     */
    public function create(User $user): bool
    {
        return $user->hasCapability('customer.segment.manage');
    }

    /**
     * Determine whether the user can update the customer segment.
     */
    public function update(User $user, CustomerSegment $customerSegment): bool
    {
        return $user->hasCapability('customer.segment.manage');
    }

    /**
     * Determine whether the user can delete the customer segment.
     */
    public function delete(User $user, CustomerSegment $customerSegment): bool
    {
        return $user->hasCapability('customer.segment.manage');
    }

    /**
     * Determine whether the user can view segment members.
     */
    public function viewMembers(User $user, CustomerSegment $customerSegment): bool
    {
        return $user->hasCapability('customer.view');
    }

    /**
     * Determine whether the user can preview segment.
     */
    public function preview(User $user): bool
    {
        return $user->hasCapability('customer.segment.manage');
    }
}