<?php

namespace App\Policies;

use App\Models\Tasks;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tasks $tasks): bool
    {
        return $user->hasRole('admin','manager') 
        || $tasks -> assigned_to === $user -> id 
        || $tasks ->project->members->contains($user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user ->hasRole('admin','manager');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tasks $tasks): bool
    {
        return $user->hasRole('admin', 'manager') || $tasks->assigned_to === $user ->id ;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tasks $tasks): bool
    {
        return $user->hasRole('admin', 'manager');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Tasks $tasks): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Tasks $tasks): bool
    {
        return false;
    }
}
