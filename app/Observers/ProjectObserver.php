<?php

namespace App\Observers;

use App\Models\Activities;
use App\Models\Projects;

class ProjectObserver
{
    /**
     * Handle the Projects "created" event.
     */
    public function created(Projects $project): void
    {
        Activities::log('created', $project, $project->name);
    }

    public function updated(Projects $project): void
    {
        if ($project->wasChanged('status') && $project->status === 'completed') {
            Activities::log('completed', $project, $project->name);
        }
    }

    public function deleted(Projects $project): void
    {
        Activities::log('archived', $project, $project->name);
    }

    public function restored(Projects $project): void
    {
        Activities::log('restored', $project, $project->name);
    }

    /**
     * Handle the Projects "force deleted" event.
     */
    public function forceDeleted(Projects $projects): void
    {
        //
    }
}
