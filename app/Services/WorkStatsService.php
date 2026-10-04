<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * This month's worked time and who is clocked in right now, for the dashboard
 * tiles and (later) the API.
 *
 * Returns plain numbers; formatting them ("16h", "3 / 4") is the caller's job.
 * A shift still running counts as a full one, since that is the most it can be
 * recorded as. Global admins are left out of every team figure.
 */
class WorkStatsService
{
    /**
     * - all_teams: an owner's every work team (when $allTeams is asked for)
     * - team: the user's current team, for owners and managers
     * - own: just the user, for everyone else
     *
     * @return array{
     *     scope: 'all_teams'|'team'|'own',
     *     team_name: ?string,
     *     is_owner: bool,
     *     is_manager: bool,
     *     worked_minutes: int,
     *     active: ?int,
     *     members: ?int,
     * }
     */
    public function forUser(User $user, bool $allTeams = false): array
    {
        $isOwner = $user->ownedTeams()->where('personal_team', false)->exists();
        $isManager = $user->isTeamManager();
        $flags = ['is_owner' => $isOwner, 'is_manager' => $isManager];

        if ($isOwner && $allTeams) {
            return ['scope' => 'all_teams', 'team_name' => null, ...$flags, ...$this->forMembers($this->allTeamsMemberIds($user))];
        }

        if ($isOwner || $isManager) {
            $team = $user->currentTeam;
            $memberIds = $team ? $this->nonAdminMembers($team)->pluck('id') : collect();

            return ['scope' => 'team', 'team_name' => $team?->name, ...$flags, ...$this->forMembers($memberIds)];
        }

        return [
            'scope' => 'own',
            'team_name' => null,
            ...$flags,
            'worked_minutes' => $this->workedMinutes(collect([$user->id])),
            'active' => null,
            'members' => null,
        ];
    }

    /**
     * @param  Collection<int, int>  $memberIds
     * @return array{worked_minutes: int, active: int, members: int}
     */
    private function forMembers(Collection $memberIds): array
    {
        return [
            'worked_minutes' => $this->workedMinutes($memberIds),
            'active' => $this->activeToday($memberIds),
            'members' => $memberIds->count(),
        ];
    }

    /**
     * Completed minutes this month, plus a full shift for each one running today.
     *
     * @param  Collection<int, int>  $userIds
     */
    private function workedMinutes(Collection $userIds): int
    {
        $completedMinutes = (int) TimeEntry::whereIn('user_id', $userIds)
            ->forMonth(now()->year, now()->month)
            ->whereNotNull('worked_minutes')
            ->sum('worked_minutes');

        return $completedMinutes + $this->activeToday($userIds) * TimeTrackingService::maxShiftHours() * 60;
    }

    /**
     * How many of these people are clocked in right now — people, not entries,
     * so someone with two open entries still counts once.
     *
     * @param  Collection<int, int>  $userIds
     */
    private function activeToday(Collection $userIds): int
    {
        return TimeEntry::whereIn('user_id', $userIds)
            ->onDay(today())
            ->active()
            ->distinct()
            ->count('user_id');
    }

    /**
     * Everyone on the owner's non-personal teams, each counted once.
     *
     * @return Collection<int, int>
     */
    private function allTeamsMemberIds(User $user): Collection
    {
        return $user->ownedTeams()
            ->where('personal_team', false)
            ->with(['users', 'owner'])
            ->get()
            ->flatMap(fn (Team $team) => $this->nonAdminMembers($team)->pluck('id'))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function nonAdminMembers(Team $team): Collection
    {
        return $team->allUsers()->reject(fn (User $member) => $member->is_admin);
    }
}
