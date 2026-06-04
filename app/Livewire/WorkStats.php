<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WorkStats extends Component
{
    public string $monthlyHours = '0h';
    public bool $isManager = false;
    public ?string $activeNow = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->activeNow = $this->computeTeamActiveNow($user);
        $this->isManager = $user->isTeamManager();

        if ($this->isManager) {
            $this->monthlyHours = $this->computeTeamMonthlyHours($user);
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

        $allUsers = $team->allUsers();
        $memberIds = $allUsers->pluck('id');

        $activeCount = TimeEntry::whereIn('user_id', $memberIds)
            ->whereDate('work_day', today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->count();

        return "{$activeCount} / {$allUsers->count()}";
    }

    /** @return Collection<int, int> */
    private function teamMemberIds(User $user): Collection
    {
        return $user->currentTeam?->allUsers()->pluck('id') ?? collect();
    }

    public function render()
    {
        return view('livewire.work-stats');
    }
}