<?php

namespace App\Providers;

use App\Events\UserClockedInEvent;
use App\Listeners\UserClockedInListener;
use App\Models\User;
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
        // Define the Global Admin Gate (God Mode)
        // This runs before all other policy checks.
        Gate::before(function (User $user, string $ability) {
            return $user->is_admin ? true : null;
            // Note: return 'null' allows the check to fall through to 
            // regular policies if the user is NOT a global admin.
        });

        // Event::listen(
        //     UserClockedInEvent::class,
        //     UserClockedInListener::class,
        // );
    }
}
