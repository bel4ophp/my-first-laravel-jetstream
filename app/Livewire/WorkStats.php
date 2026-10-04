<?php

namespace App\Livewire;

use App\Services\LeaveBalanceService;
use App\Services\WorkStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A dashboard tile: this month's hours, who is in now, and free days.
 *
 * The figures come from WorkStatsService as computed properties, so they are
 * fresh on every render — the view polls — rather than fixed at mount().
 */
class WorkStats extends Component
{
    public bool $allTeams = false;

    protected WorkStatsService $workStats;

    protected LeaveBalanceService $balances;

    public function boot(WorkStatsService $workStats, LeaveBalanceService $balances): void
    {
        $this->workStats = $workStats;
        $this->balances = $balances;
    }

    public function mount(bool $allTeams = false): void
    {
        $this->allTeams = $allTeams;
    }

    /**
     * @return array{scope: string, team_name: ?string, is_owner: bool, is_manager: bool, worked_minutes: int, active: ?int, members: ?int}
     */
    #[Computed]
    public function stats(): array
    {
        return $this->workStats->forUser(Auth::user(), $this->allTeams);
    }

    #[Computed]
    public function label(): string
    {
        return match ($this->stats['scope']) {
            'all_teams' => __('All Teams'),
            'team' => $this->stats['team_name'] ?? '',
            default => '',
        };
    }

    #[Computed]
    public function monthlyHours(): string
    {
        return intdiv($this->stats['worked_minutes'], 60).'h';
    }

    /**
     * "3 / 4" — people clocked in out of the team; null on a personal tile.
     */
    #[Computed]
    public function activeNow(): ?string
    {
        return $this->stats['active'] === null
            ? null
            : "{$this->stats['active']} / {$this->stats['members']}";
    }

    #[Computed]
    public function isOwner(): bool
    {
        return $this->stats['is_owner'];
    }

    #[Computed]
    public function isManager(): bool
    {
        return $this->stats['is_manager'];
    }

    /**
     * "total/used" pool days for the signed-in user.
     */
    #[Computed]
    public function freeDays(): string
    {
        $balance = $this->balances->currentBalance(Auth::user());

        return "{$balance->total_days}/{$balance->used_days}";
    }

    public function render(): View
    {
        return view('livewire.work-stats');
    }
}
