<?php

namespace App\Providers;

use App\Models\TimeEntry;
use App\Policies\TimeEntryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        TimeEntry::class => TimeEntryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('view-attendance', function ($user) {
            if (! $user || ! $user->currentTeam) {
                return false;
            }

            return $user->ownsTeam($user->currentTeam) || $user->hasTeamPermission($user->currentTeam, 'view-attendance');
        });
    }
}
