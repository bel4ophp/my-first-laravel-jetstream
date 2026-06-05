<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WorkStats extends Component
{
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

        $this->freeDays = '20/0';

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
            ->whereMonth('work_day', now()->month)
            ->whereYear('work_day', now()->year)
            ->whereNotNull('worked_minutes')
            ->sum('worked_minutes');

        $hasActiveToday = TimeEntry::where('user_id', $user->id)
            ->whereDate('work_day', today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
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
            ->whereMonth('work_day', now()->month)
            ->whereYear('work_day', now()->year)
            ->whereNotNull('worked_minutes')
            ->sum('worked_minutes');

        $activeCount = TimeEntry::whereIn('user_id', $memberIds)
            ->whereDate('work_day', today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
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
            ->whereDate('work_day', today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->count();

        return "{$activeCount} / {$members->count()}";
    }

    private function computeAllTeamsMonthlyHours(User $user): string
    {
        $allMemberIds = $user->ownedTeams->where('personal_team', false)->flatMap(
            fn ($team) => $this->nonAdminMembers($team)->pluck('id')
        )->unique()->values();

        $completedMinutes = TimeEntry::whereIn('user_id', $allMemberIds)
            ->whereMonth('work_day', now()->month)
            ->whereYear('work_day', now()->year)
            ->whereNotNull('worked_minutes')
            ->sum('worked_minutes');

        $activeCount = TimeEntry::whereIn('user_id', $allMemberIds)
            ->whereDate('work_day', today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->count();

        return floor(($completedMinutes + ($activeCount * 480)) / 60) . 'h';
    }

    private function computeAllTeamsActiveNow(User $user): string
    {
        $allMembers = $user->ownedTeams->where('personal_team', false)->flatMap(
            fn ($team) => $this->nonAdminMembers($team)
        )->unique('id');

        $activeCount = TimeEntry::whereIn('user_id', $allMembers->pluck('id'))
            ->whereDate('work_day', today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->count();

        return "{$activeCount} / {$allMembers->count()}";
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

    public function render()
    {
        return view('livewire.work-stats');
    }
}