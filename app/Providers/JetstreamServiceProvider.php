<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
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
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        // Safety Check for Migrations/Docker Setup
        if (app()->runningInConsole() || !Schema::hasTable('roles')) {
            // Optional: Define a hardcoded fallback here if you want
            return;
        }

        // Fetch Roles with their Permissions from DB (Cached)
        // Cache PLAIN ARRAYS to avoid "Incomplete Class" issues
        $roles = Cache::rememberForever('jetstream_roles_db', function () {
            return \App\Models\Role::with('permissions')
                ->get()
                ->map(function ($role) {
                    return [
                        'key'         => $role->key,
                        'name'        => $role->name,
                        'permissions' => $role->permissions->pluck('key')->toArray(),
                    ];
                })->toArray();
        });

        // Register each DB role into Jetstream's memory
        if (is_array($roles)) {
            foreach ($roles as $role) {
                Jetstream::role(
                    $role['key'],
                    $role['name'],
                    $role['permissions']
                )->description("Access managed via database.");
            }
        }
    }
}
