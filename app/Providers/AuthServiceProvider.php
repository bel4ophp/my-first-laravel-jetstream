<?php

namespace App\Providers;

use App\Models\TimeEntry;
use App\Policies\TimeEntryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        TimeEntry::class => TimeEntryPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}