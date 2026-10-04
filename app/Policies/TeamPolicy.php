<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Team structure is the global admin's job; a manager only brings employees in
 * and out of their own team.
 *
 * The global admin passes every check here through the Gate::before hook in
 * AppServiceProvider, so an ability that returns false is admin-only.
 *
 * Permissions are checked against the team being acted on, never the user's
 * current team — otherwise holding a permission on one team would grant it on
 * every team.
 */
class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'view-any');
    }

    /**
     * Members can view their own team.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Only the admin creates teams.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Only the admin renames teams.
     */
    public function update(User $user, Team $team): bool
    {
        return false;
    }

    /**
     * Whether the user may bring people into this team. Which roles they may
     * hand out is decided separately by TeamRole::assignableBy().
     */
    public function addTeamMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'add-team-member');
    }

    /**
     * Only the admin changes roles. A manager has nothing to change a role to:
     * the team keeps its single manager and they may only add employees.
     */
    public function updateTeamMember(User $user, Team $team): bool
    {
        return false;
    }

    /**
     * Whether the user may remove anyone from this team at all. Gates whether
     * the removal controls render; removeMember() decides each member.
     */
    public function removeTeamMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'remove-team-member');
    }

    /**
     * A manager may remove their team's employees — never themselves, so
     * nobody leaves a team on their own; the admin removes people.
     */
    public function removeMember(User $user, Team $team, User $member): bool
    {
        return ! $user->is($member)
            && $this->removeTeamMember($user, $team)
            && $this->roleOn($team, $member) === TeamRole::Employee->value;
    }

    /**
     * The member's pivot role on the team, read from the team's member list.
     *
     * The team page checks this once per row; $member->teamRole($team) would
     * load each member's own teams — one query per row. The team's list is
     * loaded once. The owner isn't on the pivot, so they have no role here.
     */
    private function roleOn(Team $team, User $member): ?string
    {
        return $team->users->firstWhere('id', $member->id)?->membership?->role;
    }

    /**
     * Manage the team's holidays and leave pool: its manager, or the admin.
     */
    public function manageLeaveSettings(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'manage-leave-settings');
    }

    /**
     * Only the admin deletes teams.
     */
    public function delete(User $user, Team $team): bool
    {
        return false;
    }
}
