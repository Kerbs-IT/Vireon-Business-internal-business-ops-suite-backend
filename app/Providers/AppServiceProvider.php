<?php

namespace App\Providers;

use App\Models\Leave_requests;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\User;
use App\Policies\LeaveRequestPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Gate::policy(User::class,UserPolicy::class);
        Gate::policy(Projects::class,ProjectPolicy::class);
        Gate::policy(Tasks::class,TaskPolicy::class);
        Gate::policy(Leave_requests::class,LeaveRequestPolicy::class);
    }
}
