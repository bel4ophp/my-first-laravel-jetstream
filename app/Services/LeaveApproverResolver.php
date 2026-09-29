<?php

namespace App\Services;

use App\Enums\TeamRole;
use App\Models\User;

class LeaveApproverResolver
{
    /**
     * Resolve who must approve a request submitted by the given user.
     * Managers are approved by the admin; everyone else by their team manager.
     */
    public function resolve(User $submitter): ?User
    {
        $team = $submitter->currentTeam;

        // Without a team there is no approval chain to walk.
        if (! $team) {
            return null;
        }

        if ($submitter->hasTeamRole($team, TeamRole::Manager->value)) {
            return User::where('is_admin', true)->orderBy('id')->first();
        }

        return User::getTeamManager($team->id);
    }
}
