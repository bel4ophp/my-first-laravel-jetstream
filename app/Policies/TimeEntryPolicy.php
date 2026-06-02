<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimeEntryPolicy
{
    use HandlesAuthorization;

    /**
     * Admin + manager can view the attendance calendar for all team members.
     * Employees are scoped to their own entries via ownership checks elsewhere.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'view-attendance');
    }

    /**
     * Own entries are always visible; admin + manager can view any team entry.
     */
    public function view(User $user, TimeEntry $timeEntry): bool
    {
        return $user->id === $timeEntry->user_id
            || $user->hasTeamPermission($user->currentTeam, 'view-attendance');
    }

    /**
     * Admin + manager can add time entries for team members.
     */
    public function create(User $user): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'create-time-entries');
    }

    /**
     * Admin + manager can edit any team member's time entry.
     */
    public function update(User $user, TimeEntry $_timeEntry): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'update-time-entries');
    }

    /**
     * Admin + manager can delete any team member's time entry.
     */
    public function delete(User $user, TimeEntry $_timeEntry): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'update-time-entries');
    }

    /**
     * Only team owners (admin) and managers can export attendance data.
     */
    public function export(User $user): bool
    {
        return $user->ownsTeam($user->currentTeam)
            || $user->hasTeamRole($user->currentTeam, 'manager');
    }
}