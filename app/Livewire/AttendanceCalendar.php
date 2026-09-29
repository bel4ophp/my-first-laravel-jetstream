<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesAttendance;
use App\Models\TimeEntry;
use App\Services\AttendanceCalendarService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Month grid and navigation for the attendance report.
 *
 * The per-day detail — entry list, inline editor, add-entry form — lives in
 * AttendanceDayPanel, which this renders for the selected day.
 */
class AttendanceCalendar extends Component
{
    use AuthorizesAttendance;

    protected AttendanceCalendarService $calendarService;

    public function boot(AttendanceCalendarService $calendarService): void
    {
        $this->calendarService = $calendarService;
    }

    #[Url]
    public int $year = 0;

    #[Url]
    public int $month = 0;

    public ?int $selectedDay = null;

    public ?int $userId = null;

    /**
     * AttendanceDayPanel writes entries; the grid caches them. Drop the cache
     * so the day cells reflect the change.
     */
    #[On('attendance-entries-changed')]
    public function refreshEntries(): void
    {
        unset($this->entriesByDay);
    }

    #[On('attendance-day-closed')]
    public function deselectDay(): void
    {
        $this->selectedDay = null;
    }

    public function mount(): void
    {
        $this->year  = $this->year  ?: now()->year;
        $this->month = $this->month ?: now()->month;
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public function prevMonth(): void
    {
        $date = $this->monthStart()->subMonth();
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

        $date = $this->monthStart()->addMonth();
        $this->year  = $date->year;
        $this->month = $date->month;
        $this->selectedDay = null;
    }

    public function updatedYear(): void
    {
        $this->clampToCurrentMonth();
    }

    public function updatedMonth(): void
    {
        $this->clampToCurrentMonth();
    }

    private function clampToCurrentMonth(): void
    {
        if ($this->year === now()->year && $this->month > now()->month) {
            $this->month = now()->month;
        }

        $this->selectedDay = null;
    }

    /**
     * Selecting the open day again closes the panel. Any in-progress edit lives
     * in AttendanceDayPanel, which is keyed on the date and so resets itself.
     */
    public function selectDay(int $day): void
    {
        $this->selectedDay = $this->selectedDay === $day ? null : $day;
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    /**
     * The user IDs this manager (or admin) is allowed to see.
     */
    #[Computed]
    public function allowedUserIds(): Collection
    {
        $user = $this->currentUser();

        if (! $user) {
            return collect();
        }

        return $this->calendarService->scopedUserIds($user, $this->userId);
    }

    /**
     * All time entries for the viewed month, grouped by day number.
     * Shape: Collection<int, Collection<TimeEntry>>
     */
    #[Computed]
    public function entriesByDay(): Collection
    {
        if (! $this->canViewTimeEntries) {
            return collect();
        }

        return TimeEntry::with('user')
            ->whereIn('user_id', $this->allowedUserIds)
            ->forMonth($this->year, $this->month)
            ->orderBy('clock_in')
            ->get()
            ->groupBy(fn (TimeEntry $e) => $e->work_day->day);
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    #[Computed]
    public function canManageTeamMembers(): bool
    {
        $team = $this->currentUser()?->currentTeam;

        return $team !== null && $this->allows('updateTeamMember', $team);
    }

    #[Computed]
    public function canViewTimeEntries(): bool
    {
        $user = $this->currentUser();

        return $user !== null && $this->allows('view', new TimeEntry(['user_id' => $user->id]));
    }

    #[Computed]
    public function canExportAttendance(): bool
    {
        return $this->allows('export', TimeEntry::class);
    }

    // ── View helpers ──────────────────────────────────────────────────────────

    #[Computed]
    public function calendarLabel(): string
    {
        return $this->monthStart()->translatedFormat('F Y');
    }

    #[Computed]
    public function daysInMonth(): int
    {
        return $this->monthStart()->daysInMonth;
    }

    /**
     * How many empty cells to prepend so the grid starts on Monday.
     */
    #[Computed]
    public function firstDayOffset(): int
    {
        $dow = $this->monthStart()->dayOfWeek; // 0 = Sunday
        return $dow === 0 ? 6 : $dow - 1;
    }

    #[Computed]
    public function selectedDateLabel(): string
    {
        if (! $this->selectedDay) {
            return '';
        }

        return Carbon::create($this->year, $this->month, $this->selectedDay)
            ->translatedFormat('l, j F Y');
    }

    #[Computed]
    public function selectedDateIso(): string
    {
        if (! $this->selectedDay) {
            return '';
        }

        return Carbon::create($this->year, $this->month, $this->selectedDay)
            ->format('Y-m-d');
    }

    #[Computed]
    public function isCurrentMonth(): bool
    {
        return $this->year === now()->year && $this->month === now()->month;
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.attendance-calendar');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function monthStart(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1);
    }
}