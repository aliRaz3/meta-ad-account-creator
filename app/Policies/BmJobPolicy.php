<?php

namespace App\Policies;

use App\Models\BmJob;
use App\Models\User;

class BmJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view the list
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BmJob $bmJob): bool
    {
        return $bmJob->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BmJob $bmJob): bool
    {
        return $bmJob->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BmJob $bmJob): bool
    {
        return $bmJob->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BmJob $bmJob): bool
    {
        return $user->isAdmin(); // Only admins can restore
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BmJob $bmJob): bool
    {
        return $user->isAdmin(); // Only admins can force delete
    }
}
