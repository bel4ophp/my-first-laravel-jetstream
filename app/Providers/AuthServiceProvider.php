<?php

namespace App\Providers;

use App\Models\LeaveRequest;
use App\Models\TimeEntry;
use App\Policies\LeaveRequestPolicy;
use App\Policies\TimeEntryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * NOT REGISTERED — this provider is intentionally absent from
 * bootstrap/providers.php and none of the code below runs.
 *
 * Laravel discovers policies by convention (App\Models\Foo => App\Policies\FooPolicy),
 * which resolves every policy this app has, so the explicit map is redundant.
 *
 * Kept as a reference for where an explicit registration would go. Two things
 * follow from that:
 *
 *  - Adding an entry to $policies here has NO effect. If a policy ever needs
 *    explicit binding — a name that breaks convention, or a policy for a model
 *    outside App\Models — either re-add this class to bootstrap/providers.php
 *    or use the #[UsePolicy] attribute on the model.
 *  - The map below is not kept in sync with app/Policies and should not be
 *    trusted as an inventory of them.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        TimeEntry::class => TimeEntryPolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}