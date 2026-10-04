<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamMemberRole;
use App\Actions\Jetstream\UpdateTeamName;
use App\Livewire\Teams\TeamMemberManager;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;
use Laravel\Jetstream\Jetstream;
use Livewire\Livewire;
use Throwable;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Jetstream resolves its role updater from the container, so this is
        // the supported way to layer the single-manager rule onto it.
        $this->app->bind(JetstreamUpdateTeamMemberRole::class, UpdateTeamMemberRole::class);
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

        // Package providers boot first, so this replaces Jetstream's registration.
        Livewire::component('teams.team-member-manager', TeamMemberManager::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        foreach ($this->databaseRoles() as $role) {
            Jetstream::role($role['key'], $role['name'], $role['permissions'])
                ->description('Access managed via database.');
        }
    }

    /**
     * The roles to register, cached as plain arrays rather than models to avoid
     * "Incomplete Class" issues on unserialization.
     *
     * This runs on every request, so a warm cache must not touch the database:
     * the table check only happens on a cache miss. The roles table is absent
     * until the first migration runs, and the database (or a database-backed
     * cache) is unreachable entirely during a container build or a fresh clone —
     * either way there is nothing to register yet.
     *
     * @return array<int, array{key: string, name: string, permissions: array<int, string>}>
     */
    private function databaseRoles(): array
    {
        try {
            $cached = Cache::get(Role::CACHE_KEY);

            if (is_array($cached)) {
                return $cached;
            }

            if (! Schema::hasTable('roles')) {
                return [];
            }

            return Cache::rememberForever(Role::CACHE_KEY, fn () => Role::with('permissions')
                ->get()
                ->map(fn (Role $role) => [
                    'key' => $role->key,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('key')->all(),
                ])->all());
        } catch (Throwable) {
            return [];
        }
    }
}
