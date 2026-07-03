<?php

namespace App\Services;

use App\Models\User;

class LeaveApproverResolver
{
    /**
     * Resolve who must approve a request submitted by the given user.
     * Managers are approved by the admin; everyone else by their team manager.
     */
    public function resolve(User $submitter): ?User
    {
        if ($submitter->hasTeamRole($submitter->currentTeam, 'manager')) {
            return User::where('is_admin', true)->first();
        }

        return User::getTeamManager($submitter->currentTeam->id);
    }
}
