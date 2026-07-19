<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class LeaveResetService
{
    public function __construct(private LeaveBalanceService $balances) {}

    /**
     * Users whose pool the actor may reset.
     *
     * Admin   -> every manager + employee across all teams.
     * Manager -> the manager themselves + their own team's employees.
     *
     * @return Collection<int, int>  user IDs
     */
    public function scopedUserIds(User $actor): Collection
    {
        if ($actor->is_admin) {
            return User::whereHas('teams', function ($query) {
                $query->whereIn('team_user.role', ['manager', 'employee']);
            })->pluck('id');
        }

        $teamId = $actor->currentTeam->id;

        return User::whereHas('teams', function ($query) use ($teamId) {
            $query->where('teams.id', $teamId)
                ->whereIn('team_user.role', ['manager', 'employee']);
        })->pluck('id');
    }

    /**
     * The users in the actor's reset scope, with the current year's balance
     * eager loaded for display.
     *
     * @return Collection<int, User>
     */
    public function scopedUsers(User $actor): Collection
    {
        return User::whereIn('id', $this->scopedUserIds($actor))
            ->with(['leaveBalances' => fn ($query) => $query->where('year', now()->year)])
            ->orderBy('name')
            ->get();
    }

    /**
     * Reset the pool for everyone in the actor's scope.
     *
     * @return int  the number of users reset
     */
    public function reset(User $actor): int
    {
        return $this->balances->resetUsedDays($this->scopedUserIds($actor));
    }
}