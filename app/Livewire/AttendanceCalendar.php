<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Jetstream;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AttendanceCalendar extends Component
{
    public int  $year;
    public int  $month;
    public ?int $selectedDay = null;

    public function mount(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public function prevMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year  = $date->year;
        $this->month = $date->month;
        $this->selectedDay = null;
    }

    public function nextMonth(): void
    {
        // Never go past the current month
        if ($this->year === now()->year && $this->month === now()->month) {
            return;
        }

        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year  = $date->year;
        $this->month = $date->month;
        $this->selectedDay = null;
    }

    public function selectDay(int $day): void
    {
        $this->selectedDay = $this->selectedDay === $day ? null : $day;
    }

    public function manageTimeEntry(int $timeEntryId): void
    {
        Gate::authorize('updateTimeEntries', TimeEntry::class);

        // TODO: Open modal or navigate to edit view for the time entry
        // For now, just dispatch an event or emit to parent component
        $this->dispatch('edit-time-entry', id: $timeEntryId);
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    /**
     * The user IDs this manager (or admin) is allowed to see.
     */
    #[Computed]
    public function allowedUserIds(): Collection
    {
        $user = Auth::user();

        // Admins see everyone
        if ($user->hasTeamRole($user->currentTeam, 'admin') || $user->ownsTeam($user->currentTeam)) {
            // Return all user IDs that belong to ANY team
            return \App\Models\User::pluck('id');
        }

        // Managers see only members of their current team
        return $user->currentTeam->allUsers()->pluck('id');
    }

    /**
     * All time entries for the viewed month, grouped by day number.
     * Shape: Collection<int, Collection<TimeEntry>>
     */
    #[Computed]
    public function entriesByDay(): Collection
    {
        return TimeEntry::with('user')
            ->whereIn('user_id', $this->allowedUserIds)
            ->whereYear('work_day',  $this->year)
            ->whereMonth('work_day', $this->month)
            ->orderBy('clock_in')
            ->get()
            ->groupBy(fn (TimeEntry $e) => (int) Carbon::parse($e->work_day)->day);
    }

    /**
     * Records for the currently selected day (for the detail panel).
     */
    #[Computed]
    public function selectedEntries(): Collection
    {
        if (! $this->selectedDay) {
            return collect();
        }

        return $this->entriesByDay->get($this->selectedDay, collect());
    }

    #[Computed]
    public function canManageTeamMembers(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->currentTeam) {
            return false;
        }

        return Gate::check('updateTeamMember', $user->currentTeam)
            && Jetstream::hasRoles();
    }

    #[Computed]
    public function canUpdateTimeEntries(): bool
    {
        return Gate::check('updateTimeEntries', TimeEntry::class)
            && Jetstream::hasRoles();
    }

    // ── View helpers ──────────────────────────────────────────────────────────

    #[Computed]
    public function calendarLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)
            ->translatedFormat('F Y');
    }

    #[Computed]
    public function daysInMonth(): int
    {
        return Carbon::create($this->year, $this->month, 1)->daysInMonth;
    }

    /**
     * How many empty cells to prepend so the grid starts on Monday.
     */
    #[Computed]
    public function firstDayOffset(): int
    {
        $dow = Carbon::create($this->year, $this->month, 1)->dayOfWeek; // 0 = Sunday
        return $dow === 0 ? 6 : $dow - 1;
    }

    #[Computed]
    public function selectedDateLabel(): string
    {
        if (! $this->selectedDay) return '';

        return Carbon::create($this->year, $this->month, $this->selectedDay)
            ->translatedFormat('l, j F Y');
    }

    #[Computed]
    public function selectedDateIso(): string
    {
        if (! $this->selectedDay) return '';

        return Carbon::create($this->year, $this->month, $this->selectedDay)
            ->format('Y-m-d');
    }

    #[Computed]
    public function isCurrentMonth(): bool
    {
        return $this->year === now()->year && $this->month === now()->month;
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.attendance-calendar');
    }
}
