<?php

namespace App\Providers;

use App\Events\UserClockedInEvent;
use App\Listeners\UserClockedInListener;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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
        // Outside production, loading a relation lazily throws, so an N+1 query
        // fails in development and in the test suite instead of slowing pages
        // down unnoticed. In production it stays a silent fallback.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Define the Global Admin Gate (God Mode)
        // This runs before all other policy checks.
        Gate::before(function (User $user, string $ability, array $arguments) {
            if (! $user->is_admin) {
                return null;
            }

            // Deleting a user is the one check the admin doesn't skip:
            // UserPolicy::delete() stops them deleting themselves or another
            // admin, which would lock everyone out of role management.
            if ($ability === 'delete' && ($arguments[0] ?? null) instanceof User) {
                return null;
            }

            return true;
        });

        // Event::listen(
        //     UserClockedInEvent::class,
        //     UserClockedInListener::class,
        // );
    }
}
