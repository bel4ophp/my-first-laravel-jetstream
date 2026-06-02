<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceCalendarService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class AttendanceCalendar extends Component
{
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
    public ?int $userId      = null;

    // ── Bulk inline editing state ─────────────────────────────────────────────
    public bool  $editingEntries = false;
    public array $entryEdits     = [];

    // ── Create new entry state ────────────────────────────────────────────────
    public bool  $creatingEntry = false;
    public array $createForm    = [
        'user_id'   => null,
        'clock_in'  => '',
        'clock_out' => null,
    ];

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

    public function selectDay(int $day): void
    {
        if ($this->selectedDay === $day) {
            $this->selectedDay = null;
            $this->stopEditingEntries();
            $this->cancelCreatingEntry();

            return;
        }

        $this->selectedDay = $day;
        $this->stopEditingEntries();
        $this->cancelCreatingEntry();
    }

    // ── Bulk inline editing ───────────────────────────────────────────────────

    public function toggleEditingEntries(): void
    {
        $this->editingEntries = ! $this->editingEntries;

        if ($this->editingEntries) {
            $this->initializeEntryEdits();
        } else {
            $this->stopEditingEntries();
        }
    }

    public function initializeEntryEdits(): void
    {
        $this->entryEdits = $this->selectedEntries->mapWithKeys(function (TimeEntry $entry) {
            return [$entry->id => [
                'clock_in'  => $entry->clockInFormatted(),
                'clock_out' => $entry->clockOutFormatted(),
            ]];
        })->toArray();
    }

    public function stopEditingEntries(): void
    {
        $this->editingEntries = false;
        $this->entryEdits     = [];
        $this->cancelCreatingEntry();
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = TimeEntry::findOrFail($entryId);
        Gate::authorize('delete', $entry);
        $entry->delete();
    }

    public function saveEntryEdits(int $entryId): void
    {
        if (! isset($this->entryEdits[$entryId])) {
            return;
        }

        $entry = TimeEntry::findOrFail($entryId);
        Gate::authorize('update', $entry);

        $this->validate([
            "entryEdits.$entryId.clock_in"  => ['required', 'date_format:H:i'],
            "entryEdits.$entryId.clock_out" => ['nullable', 'date_format:H:i'],
        ]);

        try {
            $this->calendarService->updateTimeEntry(
                $entry,
                $this->entryEdits[$entryId]['clock_in'],
                $this->entryEdits[$entryId]['clock_out'],
            );
        } catch (\InvalidArgumentException) {
            $this->addError("entryEdits.$entryId.clock_out", 'Clock out must be after clock in.');
            return;
        }

        $this->initializeEntryEdits();
    }

    // ── Create new entry ──────────────────────────────────────────────────────

    public function startCreatingEntry(): void
    {
        $this->creatingEntry = true;
        $this->createForm    = [
            'user_id'   => null,
            'clock_in'  => '',
            'clock_out' => null,
        ];
    }

    public function cancelCreatingEntry(): void
    {
        $this->creatingEntry = false;
        $this->createForm    = [
            'user_id'   => null,
            'clock_in'  => '',
            'clock_out' => null,
        ];
    }

    public function saveNewEntry(): void
    {
        Gate::authorize('create', TimeEntry::class);

        $this->validate([
            'createForm.user_id'   => ['required', 'integer', Rule::in($this->selectableUsers->pluck('id'))],
            'createForm.clock_in'  => ['required', 'date_format:H:i'],
            'createForm.clock_out' => ['nullable', 'date_format:H:i'],
        ]);

        $workDay = Carbon::create($this->year, $this->month, $this->selectedDay)->format('Y-m-d');

        try {
            $this->calendarService->createTimeEntry(
                (int) $this->createForm['user_id'],
                $workDay,
                $this->createForm['clock_in'],
                $this->createForm['clock_out'],
            );
        } catch (\InvalidArgumentException) {
            $this->addError('createForm.clock_out', 'Clock out must be after clock in.');
            return;
        }

        // In edit mode keep the form open for the next entry; otherwise close it
        if ($this->editingEntries) {
            $this->createForm = [
                'user_id'   => null,
                'clock_in'  => '',
                'clock_out' => null,
            ];
        } else {
            $this->cancelCreatingEntry();
        }
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
     * Users available for the new-entry employee selector.
     */
    #[Computed]
    public function selectableUsers(): Collection
    {
        $user = $this->currentUser();

        if (! $user) {
            return collect();
        }

        return $this->calendarService->selectableUsers($user);
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
        $user = $this->currentUser();

        return $user !== null
            && $user->currentTeam !== null
            && Gate::check('updateTeamMember', $user->currentTeam);
    }

    #[Computed]
    public function canUpdateTimeEntries(): bool
    {
        return $this->currentUser() !== null
            && Gate::check('update', new TimeEntry());
    }

    #[Computed]
    public function canCreateTimeEntries(): bool
    {
        return $this->currentUser() !== null
            && Gate::check('create', TimeEntry::class);
    }

    #[Computed]
    public function canDeleteTimeEntries(): bool
    {
        return $this->currentUser() !== null
            && Gate::check('delete', new TimeEntry());
    }

    #[Computed]
    public function canExportAttendance(): bool
    {
        return $this->currentUser() !== null
            && Gate::check('export', TimeEntry::class);
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

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}