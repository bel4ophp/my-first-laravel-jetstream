<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WorkStats extends Component
{
    protected LeaveBalanceService $balances;

    public function boot(LeaveBalanceService $balances): void
    {
        $this->balances = $balances;
    }

    public bool $allTeams = false;
    public string $label = '';
    public string $monthlyHours = '0h';
    public bool $isManager = false;
    public bool $isOwner = false;
    public ?string $activeNow = null;
    public ?string $freeDays = null;

    public function mount(bool $allTeams = false): void
    {
        $this->allTeams = $allTeams;

        /** @var User $user */
        $user = Auth::user();
        $this->isOwner = $user->ownedTeams()->where('personal_team', false)->exists();
        $this->isManager = $user->isTeamManager();

        $balance = $this->balances->currentBalance($user);
        $this->freeDays = "{$balance->total_days}/{$balance->used_days}";

        if ($this->isOwner && $allTeams) {
            $this->label = 'All Teams';
            $this->monthlyHours = $this->computeAllTeamsMonthlyHours($user);
            $this->activeNow = $this->computeAllTeamsActiveNow($user);
        } elseif ($this->isOwner || $this->isManager) {
            $this->label = $user->currentTeam?->name ?? '';
            $this->monthlyHours = $this->computeTeamMonthlyHours($user);
            $this->activeNow = $this->computeTeamActiveNow($user);
        } else {
            $this->monthlyHours = $this->computeMyMonthlyHours($user);
        }
    }

    private function computeMyMonthlyHours(User $user): string
    {
        $completedMinutes = TimeEntry::where('user_id', $user->id)
            ->forMonth(now()->year, now()->month)
            ->whereNotNull('worked_minutes')
            ->sum('worked_minutes');

        $hasActiveToday = TimeEntry::where('user_id', $user->id)
            ->onDay(today())
            ->active()
            ->exists();

        return floor(($completedMinutes + ($hasActiveToday ? 480 : 0)) / 60) . 'h';
    }

    private function computeTeamMonthlyHours(User $user): string
    {
        $team = $user->currentTeam;

        if (! $team) {
            return '0h';
        }

        $memberIds = $this->teamMemberIds($user);

        $completedMinutes = TimeEntry::whereIn('user_id', $memberIds)
            ->forMonth(now()->year, now()->month)
            ->whereNotNull('worked_minutes')
            ->sum('worked_minutes');

        $activeCount = TimeEntry::whereIn('user_id', $memberIds)
            ->onDay(today())
            ->active()
            ->count();

        return floor(($completedMinutes + ($activeCount * 480)) / 60) . 'h';
    }

    private function computeTeamActiveNow(User $user): string
    {
        $team = $user->currentTeam;

        if (! $team) {
            return '0 / 0';
        }

        $members = $this->nonAdminMembers($team);
        $memberIds = $members->pluck('id');

        $activeCount = TimeEntry::whereIn('user_id', $memberIds)
            ->onDay(today())
            ->active()
            ->count();

        return "{$activeCount} / {$members->count()}";
    }

    private function computeAllTeamsMonthlyHours(User $user): string
    {
        $allMemberIds = $this->workTeams($user)->flatMap(
            fn ($team) => $this->nonAdminMembers($team)->pluck('id')
        )->unique()->values();

        $completedMinutes = TimeEntry::whereIn('user_id', $allMemberIds)
            ->forMonth(now()->year, now()->month)
            ->whereNotNull('worked_minutes')
            ->sum('worked_minutes');

        $activeCount = TimeEntry::whereIn('user_id', $allMemberIds)
            ->onDay(today())
            ->active()
            ->count();

        return floor(($completedMinutes + ($activeCount * 480)) / 60) . 'h';
    }

    private function computeAllTeamsActiveNow(User $user): string
    {
        $allMembers = $this->workTeams($user)->flatMap(
            fn ($team) => $this->nonAdminMembers($team)
        )->unique('id');

        $activeCount = TimeEntry::whereIn('user_id', $allMembers->pluck('id'))
            ->onDay(today())
            ->active()
            ->count();

        return "{$activeCount} / {$allMembers->count()}";
    }

    /**
     * The owner's non-personal teams, with members and owners eager loaded so
     * nonAdminMembers() doesn't cost two queries per team.
     *
     * @return Collection<int, Team>
     */
    private function workTeams(User $user): Collection
    {
        return $user->ownedTeams()
            ->where('personal_team', false)
            ->with(['users', 'owner'])
            ->get();
    }

    /** @return Collection<int, int> */
    private function teamMemberIds(User $user): Collection
    {
        if (! $user->currentTeam) {
            return collect();
        }

        return $this->nonAdminMembers($user->currentTeam)->pluck('id');
    }

    /** @return Collection<int, User> */
    private function nonAdminMembers(Team $team): Collection
    {
        return $team->allUsers()->reject(fn (User $member) => $member->is_admin);
    }

    public function render(): View
    {
        return view('livewire.work-stats');
    }
}