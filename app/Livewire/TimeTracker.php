<?php

namespace App\Livewire;

use App\Services\TimeTrackingService;
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

    protected TimeTrackingService $timeTracking;

    public function boot(TimeTrackingService $timeTracking): void
    {
        $this->timeTracking = $timeTracking;
    }

    public function mount(): void
    {
        $this->loadEntry();
    }

    /**
     * Sync the display state from today's entry.
     *
     * The entry itself is deliberately not a public property: Livewire would
     * serialize the whole model into the component payload on every request.
     * The properties set here are for display only — the browser can edit
     * them, so the service never trusts them.
     */
    public function loadEntry(): void
    {
        $entry = $this->timeTracking->todaysEntry(Auth::user());

        // Explicitly sync the values Alpine reads.
        $this->clockInTime = $entry?->clock_in;
        $this->clockOutTime = $entry?->clock_out;
        $this->workedMinutes = $entry?->worked_minutes ?? 0;

        // Running once there is a clock-in but no clock-out yet.
        $this->isRunning = $this->clockInTime !== null && $this->clockOutTime === null;
    }

    public function clockIn(): void
    {
        $this->timeTracking->clockIn(Auth::user());

        $this->loadEntry();
    }

    public function clockOut(): void
    {
        $this->timeTracking->clockOut(Auth::user());

        $this->loadEntry();
    }

    public function render(): View
    {
        return view('livewire.time-tracker');
    }
}
