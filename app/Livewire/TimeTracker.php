<?php

namespace App\Livewire;

use App\Events\UserClockedInEvent;
use App\Models\TimeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TimeTracker extends Component
{
    public ?Carbon $clockInTime = null;

    public ?Carbon $clockOutTime = null;

    public int $workedMinutes = 0;

    public bool $isRunning = false;

    public function mount(): void
    {
        $this->loadEntry();
    }

    /**
     * Today's entry for the signed-in user, or null before they clock in.
     *
     * Deliberately not a public property: Livewire would serialize the whole
     * model into the component payload on every request.
     */
    private function todaysEntry(): ?TimeEntry
    {
        return TimeEntry::where('user_id', Auth::id())
            ->onDay(today())
            ->first();
    }

    public function loadEntry(): void
    {
        $entry = $this->todaysEntry();

        // Explicitly sync the values Alpine reads.
        $this->clockInTime = $entry?->clock_in;
        $this->clockOutTime = $entry?->clock_out;
        $this->workedMinutes = $entry?->worked_minutes ?? 0;

        // Running once there is a clock-in but no clock-out yet.
        $this->isRunning = $this->clockInTime !== null && $this->clockOutTime === null;
    }

    public function clockIn(): void
    {
        if ($this->isRunning) {
            return;
        }

        $entry = $this->todaysEntry();

        if ($entry) {
            $entry->update(['clock_in' => now()]);
        } else {
            $entry = TimeEntry::create([
                'user_id' => Auth::id(),
                'work_day' => today(),
                'clock_in' => now(),
            ]);
        }

        $this->loadEntry();

        event(new UserClockedInEvent(Auth::user(), $entry));
    }

    public function clockOut(): void
    {
        $entry = $this->todaysEntry();

        if (! $this->isRunning || ! $entry) {
            return;
        }

        $entry->update([
            'clock_out' => now(),
            'worked_minutes' => $entry->clock_in->diffInMinutes(now()),
        ]);

        $this->loadEntry();
    }

    public function render(): View
    {
        return view('livewire.time-tracker');
    }
}
