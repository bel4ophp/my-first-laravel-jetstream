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
    public ?int $userId = null;
    public bool $editingEntries = false;
    public array $entryEdits = [];
    public ?int $editingTimeEntryId = null;
    public bool $editingTimeEntry = false;
    public array $editForm = [
        'clock_in' => '',
        'clock_out' => null,
    ];

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
        if ($this->selectedDay === $day) {
            $this->selectedDay = null;
            $this->stopEditingEntries();

            return;
        }

        $this->selectedDay = $day;
        $this->stopEditingEntries();
    }

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
                'clock_in' => $entry->clockInFormatted(),
                'clock_out' => $entry->clockOutFormatted(),
            ]];
        })->toArray();
    }

    public function stopEditingEntries(): void
    {
        $this->editingEntries = false;
        $this->entryEdits = [];
    }

    public function saveEntryEdits(int $entryId): void
    {
        if (! isset($this->entryEdits[$entryId])) {
            return;
        }

        $entry = TimeEntry::findOrFail($entryId);

        Gate::authorize('update', $entry);

        $this->validate([
            "entryEdits.$entryId.clock_in" => ['required', 'date_format:H:i'],
            "entryEdits.$entryId.clock_out" => ['nullable', 'date_format:H:i'],
        ]);

        $date = Carbon::parse($entry->work_day)->format('Y-m-d');
        $clockIn = Carbon::createFromFormat('Y-m-d H:i', "$date {$this->entryEdits[$entryId]['clock_in']}");
        $clockOut = null;

        if ($this->entryEdits[$entryId]['clock_out']) {
            $clockOut = Carbon::createFromFormat('Y-m-d H:i', "$date {$this->entryEdits[$entryId]['clock_out']}");

            if ($clockOut->lt($clockIn)) {
                $this->addError("entryEdits.$entryId.clock_out", 'Clock out must be after clock in.');
                return;
            }
        }

        $entry->clock_in = $clockIn;
        $entry->clock_out = $clockOut;
        $entry->worked_minutes = $clockOut ? $clockIn->diffInMinutes($clockOut) : null;
        $entry->save();

        $this->initializeEntryEdits();
    }

    public function manageTimeEntry(int $timeEntryId): void
    {
        $this->startEditingTimeEntry($timeEntryId);
    }

    public function startEditingTimeEntry(int $timeEntryId): void
    {
        $entry = TimeEntry::with('user')->findOrFail($timeEntryId);

        Gate::authorize('update', $entry);

        $this->editingTimeEntryId = $entry->id;
        $this->editingTimeEntry = true;
        $this->editForm = [
            'clock_in' => $entry->clockInFormatted(),
            'clock_out' => $entry->clockOutFormatted(),
        ];
    }

    public function stopEditingTimeEntry(): void
    {
        $this->editingTimeEntry = false;
        $this->editingTimeEntryId = null;
        $this->editForm = [
            'clock_in' => '',
            'clock_out' => null,
        ];
    }

    public function saveEditedTimeEntry(): void
    {
        $entry = TimeEntry::findOrFail($this->editingTimeEntryId);

        Gate::authorize('update', $entry);

        $this->validate([
            'editForm.clock_in' => ['required', 'date_format:H:i'],
            'editForm.clock_out' => ['nullable', 'date_format:H:i'],
        ]);

        $date = Carbon::parse($entry->work_day)->format('Y-m-d');
        $clockIn = Carbon::createFromFormat('Y-m-d H:i', "$date {$this->editForm['clock_in']}");
        $clockOut = null;

        if ($this->editForm['clock_out']) {
            $clockOut = Carbon::createFromFormat('Y-m-d H:i', "$date {$this->editForm['clock_out']}");

            if ($clockOut->lt($clockIn)) {
                $this->addError('editForm.clock_out', 'Clock out must be after clock in.');
                return;
            }
        }

        $entry->clock_in = $clockIn;
        $entry->clock_out = $clockOut;
        $entry->worked_minutes = $clockOut ? $clockIn->diffInMinutes($clockOut) : null;
        $entry->save();

        $this->stopEditingTimeEntry();
    }

    #[Computed]
    public function editEntry(): ?TimeEntry
    {
        if (! $this->editingTimeEntryId) {
            return null;
        }

        return TimeEntry::with('user')->find($this->editingTimeEntryId);
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    /**
     * The user IDs this manager (or admin) is allowed to see.
     */
    #[Computed]
    public function allowedUserIds(): Collection
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        if ($this->userId) {
            return User::whereKey($this->userId)->pluck('id');
        }

        // Admins see everyone
        if ($user->hasTeamRole($user->currentTeam, 'admin') || $user->ownsTeam($user->currentTeam)) {
            // Return all user IDs that belong to ANY team
            return User::pluck('id');
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
