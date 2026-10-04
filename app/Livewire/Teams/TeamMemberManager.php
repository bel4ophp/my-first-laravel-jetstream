<?php

namespace App\Livewire\Teams;

use App\Enums\TeamRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager as JetstreamTeamMemberManager;
use Laravel\Jetstream\Role;

/**
 * Jetstream's team member manager with the invitation check it lacks.
 *
 * Registered under Jetstream's own component name in JetstreamServiceProvider,
 * so the team page renders this class.
 */
class TeamMemberManager extends JetstreamTeamMemberManager
{
    /**
     * Jetstream only scopes the delete to this team, so any member who can open
     * the team page — an employee included — could cancel its invitations.
     * Cancelling is limited to whoever may invite.
     *
     * @param  int  $invitationId
     */
    public function cancelTeamInvitation($invitationId): void
    {
        Gate::authorize('addTeamMember', $this->team);

        parent::cancelTeamInvitation($invitationId);
    }

    /**
     * The roles the signed-in user may hand out on this team, so the pickers
     * offer exactly what the add, invite and role-change actions accept.
     *
     * @return Collection<int, Role>
     */
    public function getAssignableRolesProperty(): Collection
    {
        $assignable = TeamRole::assignableBy($this->user, $this->team);

        return collect($this->roles)
            ->filter(fn (Role $role) => in_array($role->key, $assignable, true))
            ->values();
    }
}
