<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimeEntryPolicy
{
    use HandlesAuthorization;

    public function updateTimeEntries(User $user): bool
    {
        return $user->ownsTeam($user->currentTeam)
            || $user->hasTeamPermission($user->currentTeam, 'update-time-entries');
    }

    public function update(User $user, TimeEntry $timeEntry): bool
    {
        return $user->ownsTeam($user->currentTeam)
            || $user->hasTeamPermission($user->currentTeam, 'update-time-entries');
    }
}
