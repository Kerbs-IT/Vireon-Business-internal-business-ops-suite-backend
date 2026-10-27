<?php

namespace App\Policies;

use App\Models\Leave_requests;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeaveRequestPolicy
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
    public function view(User $user, Leave_requests $leaveRequests): bool
    {
        return $user->hasRole('admin','manager') 
        || $leaveRequests->user_id === $user->id
        || $user ->hasRole('manager') && $leaveRequests-> user ->department_id === $user ->department_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Leave_requests $leaveRequests): bool
    {
        return true;
    }

    public function approve(User $user, Leave_requests $leaveRequest): bool
    {
        return $user->hasRole('admin', 'manager');
    }

    public function reject(User $user, Leave_requests $leaveRequest): bool
    {
        return $user->hasRole('admin', 'manager');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Leave_requests $leaveRequests): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Leave_requests $leaveRequests): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Leave_requests $leaveRequests): bool
    {
        return false;
    }
}
