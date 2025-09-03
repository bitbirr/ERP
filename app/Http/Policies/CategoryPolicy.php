<?php

namespace App\Http\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasCapability('category.view');
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->hasCapability('category.view');
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasCapability('category.create');
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->hasCapability('category.update');
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->hasCapability('category.delete');
    }

    /**
     * Determine whether the user can assign customers to categories.
     */
    public function assignCustomer(User $user): bool
    {
        return $user->hasCapability('category.assign');
    }

    /**
     * Determine whether the user can remove customers from categories.
     */
    public function removeCustomer(User $user): bool
    {
        return $user->hasCapability('category.assign');
    }

    /**
     * Determine whether the user can view category statistics.
     */
    public function viewStats(User $user): bool
    {
        return $user->hasCapability('category.view');
    }
}