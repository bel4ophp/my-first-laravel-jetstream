<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * View users list (admin + team owner)
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->ownsTeam($user->currentTeam);
    }

    /**
     * View a single user. Scoped to the teams the actor owns, so one owner
     * cannot read a user who belongs only to another owner's team.
     */
    public function view(User $user, User $target): bool
    {
        return $this->ownsATeamOf($user, $target);
    }

    /**
     * Create a user (admin + team owner)
     */
    public function create(User $user): bool
    {
        return $user->is_admin || $user->ownsTeam($user->currentTeam);
    }

    /**
     * Update a user. Same team scoping as view().
     */
    public function update(User $user, User $target): bool
    {
        return $this->ownsATeamOf($user, $target);
    }

    /**
     * Delete a user. Global admins are never deletable through this screen —
     * removing the last admin would lock everyone out of role management.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->is($target) || $target->is_admin) {
            return false;
        }

        return $this->ownsATeamOf($user, $target);
    }

    /**
     * Whether the target belongs to at least one team the actor owns.
     *
     * A global admin bypasses this entirely via the Gate::before hook in
     * AppServiceProvider, so this only gates team owners.
     */
    private function ownsATeamOf(User $user, User $target): bool
    {
        return $target->teams()
            ->whereIn('teams.id', $user->ownedTeams()->select('teams.id'))
            ->exists();
    }
}
