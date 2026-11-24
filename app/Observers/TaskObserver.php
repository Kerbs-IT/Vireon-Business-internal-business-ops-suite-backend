<?php

namespace App\Observers;

use App\Models\Activities;
use App\Models\Tasks;

class TaskObserver
{
    /**
     * Handle the Tasks "created" event.
     */
    public function created(Tasks $task): void
    {
        Activities::log('created', $task, $task->title, $task->assignee?->department_id);
    }

    public function updated(Tasks $task): void
    {
        // only log meaningful changes, not every edit
        if ($task->wasChanged('status')) {
            $action = $task->status === 'done' ? 'completed' : 'moved';
            Activities::log($action, $task, $task->title, $task->assignee?->department_id);
        }
        if($task->isDirty('status')){
            $task->completed_at = $task->status === 'done' ? now() : null;
        }
    }

    /**
     * Handle the Tasks "deleted" event.
     */
    public function deleted(Tasks $tasks): void
    {
        //
    }

    /**
     * Handle the Tasks "restored" event.
     */
    public function restored(Tasks $tasks): void
    {
        //
    }

    /**
     * Handle the Tasks "force deleted" event.
     */
    public function forceDeleted(Tasks $tasks): void
    {
        //
    }
}
