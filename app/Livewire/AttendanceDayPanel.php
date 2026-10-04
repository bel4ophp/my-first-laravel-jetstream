<?php

namespace App\Livewire;

use App\Exceptions\InvalidClockTimes;
use App\Livewire\Concerns\AuthorizesAttendance;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceCalendarService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * The detail panel for one day of the attendance calendar: the entry list, the
 * inline bulk editor and the add-entry form.
 *
 * Split out of AttendanceCalendar, which now owns only navigation and the grid.
 * The parent keys this component on the date, so switching days remounts it and
 * any half-finished edit resets on its own.
 */
class AttendanceDayPanel extends Component
{
    use AuthorizesAttendance;

    /** The day being shown, as Y-m-d. */
    #[Reactive]
    public string $date = '';

    /** Optional single-employee filter, mirroring the calendar's own. */
    #[Reactive]
    public ?int $userId = null;

    // ── Bulk inline editing state ─────────────────────────────────────────────
    public bool $editingEntries = false;

    public array $entryEdits = [];

    // ── Create new entry state ────────────────────────────────────────────────
    public bool $creatingEntry = false;

    public array $createForm = [
        'user_id' => null,
        'clock_in' => '',
        'clock_out' => null,
    ];

    protected AttendanceCalendarService $calendarService;

    public function boot(AttendanceCalendarService $calendarService): void
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Tell the calendar to drop its cached grid after anything is written.
     */
    private function announceChange(): void
    {
        $this->dispatch('attendance-entries-changed');
    }

    public function close(): void
    {
        $this->dispatch('attendance-day-closed');
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
        $this->entryEdits = $this->entries->mapWithKeys(fn (TimeEntry $entry) => [
            $entry->id => [
                'clock_in' => $entry->clockInFormatted(),
                'clock_out' => $entry->clockOutFormatted(),
            ],
        ])->toArray();
    }

    public function stopEditingEntries(): void
    {
        $this->editingEntries = false;
        $this->entryEdits = [];
        $this->cancelCreatingEntry();
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = TimeEntry::findOrFail($entryId);
        Gate::authorize('delete', $entry);

        $entry->delete();

        unset($this->entries);
        $this->announceChange();
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

        try {
            $this->calendarService->updateTimeEntry(
                $entry,
                $this->entryEdits[$entryId]['clock_in'],
                $this->entryEdits[$entryId]['clock_out'],
            );
        } catch (InvalidClockTimes $e) {
            $this->addError("entryEdits.$entryId.clock_out", $e->getMessage());

            return;
        }

        unset($this->entries);
        $this->initializeEntryEdits();
        $this->announceChange();
    }

    // ── Create new entry ──────────────────────────────────────────────────────

    public function startCreatingEntry(): void
    {
        $this->creatingEntry = true;
        $this->resetCreateForm();
    }

    public function cancelCreatingEntry(): void
    {
        $this->creatingEntry = false;
        $this->resetCreateForm();
    }

    private function resetCreateForm(): void
    {
        $this->createForm = [
            'user_id' => null,
            'clock_in' => '',
            'clock_out' => null,
        ];
    }

    public function saveNewEntry(): void
    {
        Gate::authorize('create', TimeEntry::class);

        $this->validate([
            'createForm.user_id' => ['required', 'integer', Rule::in($this->selectableUsers->pluck('id'))],
            'createForm.clock_in' => ['required', 'date_format:H:i'],
            'createForm.clock_out' => ['nullable', 'date_format:H:i'],
        ]);

        try {
            $this->calendarService->createTimeEntry(
                (int) $this->createForm['user_id'],
                $this->date,
                $this->createForm['clock_in'],
                $this->createForm['clock_out'],
            );
        } catch (InvalidClockTimes $e) {
            $this->addError('createForm.clock_out', $e->getMessage());

            return;
        }

        unset($this->entries);

        // In edit mode keep the form open for the next entry; otherwise close it
        if ($this->editingEntries) {
            $this->resetCreateForm();
            $this->initializeEntryEdits();
        } else {
            $this->cancelCreatingEntry();
        }

        $this->announceChange();
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, TimeEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        $user = $this->currentUser();

        if (! $user || ! $this->canViewTimeEntries) {
            return collect();
        }

        return TimeEntry::with('user')
            ->whereIn('user_id', $this->calendarService->scopedUserIds($user, $this->userId))
            ->onDay($this->date)
            ->orderBy('clock_in')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function selectableUsers(): Collection
    {
        $user = $this->currentUser();

        return $user ? $this->calendarService->selectableUsers($user) : collect();
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    #[Computed]
    public function canCreateTimeEntries(): bool
    {
        return $this->allows('create', TimeEntry::class);
    }

    /**
     * Only decides whether the edit controls render. Each entry is still
     * authorized on its own in saveEntryEdits().
     */
    #[Computed]
    public function canUpdateTimeEntries(): bool
    {
        return $this->allows('manage', TimeEntry::class);
    }

    /**
     * Only decides whether the delete buttons render. Each entry is still
     * authorized on its own in deleteEntry().
     */
    #[Computed]
    public function canDeleteTimeEntries(): bool
    {
        return $this->allows('manage', TimeEntry::class);
    }

    // ── View helpers ──────────────────────────────────────────────────────────

    #[Computed]
    public function dateLabel(): string
    {
        return Carbon::parse($this->date)->translatedFormat('l, j F Y');
    }

    public function render(): View
    {
        return view('livewire.attendance-day-panel');
    }
}
