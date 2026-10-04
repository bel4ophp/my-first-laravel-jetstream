<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceCalendarService;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimeEntryPolicy
{
    use HandlesAuthorization;

    public function __construct(private AttendanceCalendarService $calendarService) {}

    /**
     * Every role holds view-attendance, so everyone on a team can open the
     * attendance calendar and see their whole team's clock-ins — employees use
     * it to see who is in today. AttendanceCalendarService::scopedUserIds()
     * keeps that to their own team; changing entries needs manage().
     */
    public function viewAny(User $user): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'view-attendance');
    }

    /**
     * Own entries are always visible; anyone with view-attendance can see their
     * team's entries (which entries reach them is AttendanceCalendarService's call).
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
     * Whether the user edits and deletes time entries at all, regardless of
     * whose. The UI reads this to decide whether to render the controls; the
     * actions themselves must still authorize update/delete per entry.
     */
    public function manage(User $user): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'update-time-entries');
    }

    /**
     * Admin + manager can edit a time entry belonging to someone they can see.
     */
    public function update(User $user, TimeEntry $timeEntry): bool
    {
        return $this->manage($user) && $this->isInScope($user, $timeEntry);
    }

    /**
     * Admin + manager can delete a time entry belonging to someone they can see.
     */
    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        return $this->manage($user) && $this->isInScope($user, $timeEntry);
    }

    /**
     * Only team owners (admin) and managers can export attendance data.
     */
    public function export(User $user): bool
    {
        return $user->ownsTeam($user->currentTeam)
            || $user->hasTeamRole($user->currentTeam, TeamRole::Manager->value);
    }

    /**
     * Whether the entry belongs to someone whose attendance the user may see.
     *
     * Holding the permission is not enough on its own: without this, a manager
     * could edit or delete any team's entry by passing its ID to a Livewire
     * action. Scope comes from the same service the calendar lists from, so
     * what a manager can touch is exactly what they are shown.
     */
    private function isInScope(User $user, TimeEntry $timeEntry): bool
    {
        return $this->calendarService->scopedUserIds($user, null)->contains($timeEntry->user_id);
    }
}
