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
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;
use Laravel\Jetstream\Jetstream;
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
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        if (! $this->rolesTableIsReady()) {
            return;
        }

        /**
         * Cached as plain arrays rather than models to avoid "Incomplete Class"
         * issues on unserialization.
         *
         * @var array<int, array{key: string, name: string, permissions: array<int, string>}> $roles
         */
        $roles = Cache::rememberForever(Role::CACHE_KEY, function () {
            return Role::with('permissions')
                ->get()
                ->map(fn (Role $role) => [
                    'key' => $role->key,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('key')->all(),
                ])->all();
        });

        foreach ($roles as $role) {
            Jetstream::role($role['key'], $role['name'], $role['permissions'])
                ->description('Access managed via database.');
        }
    }

    /**
     * The roles table is absent until the first migration runs, and the database
     * is unreachable entirely during a container build or a fresh clone. Either
     * way there is nothing to register yet.
     */
    private function rolesTableIsReady(): bool
    {
        try {
            return Schema::hasTable('roles');
        } catch (Throwable) {
            return false;
        }
    }
}
