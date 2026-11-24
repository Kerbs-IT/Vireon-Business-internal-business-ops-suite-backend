<?php

namespace App\Observers;

use App\Models\Activities;
use App\Models\leave_requests;

class LeaveRequestObserver
{
    /**
     * Handle the leave_requests "created" event.
     */
    public function created(leave_requests $leave_requests): void
    {
        //
        Activities::log('submitted',$leave_requests,'leave request', $leave_requests -> user?->department_id);
    }

    /**
     * Handle the leave_requests "updated" event.
     */
    public function updated(leave_requests $leave_requests): void
    {
        //
        if ($leave_requests->wasChanged('status') && in_array($leave_requests->status, ['approved', 'rejected'], true)) {
            Activities::log($leave_requests->status, $leave_requests, 'leave request', $leave_requests->user?->department_id);
        }

        // a pending request edited by its owner
        if ($leave_requests->status === 'pending' && $leave_requests->wasChanged(['start_date', 'end_date', 'leave_type_id', 'reason'])) {
            Activities::log('updated', $leave_requests, 'leave request', $leave_requests->user?->department_id);
        }
    }

    /**
     * Handle the leave_requests "deleted" event.
     */
    public function deleted(leave_requests $leave_requests): void
    {
        //
    }

    /**
     * Handle the leave_requests "restored" event.
     */
    public function restored(leave_requests $leave_requests): void
    {
        //
    }

    /**
     * Handle the leave_requests "force deleted" event.
     */
    public function forceDeleted(leave_requests $leave_requests): void
    {
        //
    }
}
