<?php

namespace App\Livewire;

use App\Events\UserClockedInEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Notifications\TimeTrackerNotification;
use Livewire\Component;

class TimeTracker extends Component
{
    public $entry;
    public $elapsed;

    public $clockInTime = null;
    public $clockOutTime = null;
    public $workedMinutes = 0;

    public $isRunning = false;

    public function mount()
    {
        $this->loadEntry();
    }

    public function loadEntry()
    {
        $this->entry = TimeEntry::where('user_id', auth()->id())
            ->whereDate('work_day', today())
            ->first();

        // explicitly sync the values Alpine needs
        $this->clockInTime = $this->entry?->clock_in;
        $this->clockOutTime = $this->entry?->clock_out;
        $this->workedMinutes = $this->entry?->worked_minutes ?? 0;

        // Running if we have a clock-in but no clock-out yet
        $this->isRunning = $this->clockInTime && !$this->clockOutTime;
    }

    public function clockIn()
    {
        if ($this->isRunning) {
            return;
        }

        if ($this->entry) {
            $this->entry->update([
                'clock_in' => now(),
            ]);
        } else {
            TimeEntry::create([
                'user_id'  => auth()->id(),
                'work_day' => today(),
                'clock_in' => now(),
            ]);
        }

        // always reload from DB
        $this->loadEntry();

        event(new UserClockedInEvent(
            auth()->user(),
            $this->entry
        ));
    }

    public function clockOut()
    {
        if (!$this->isRunning || !$this->entry) {
            return;
        }

        $this->entry->update([
            'clock_out' => now(),
            'worked_minutes' => $this->entry->clock_in->diffInMinutes(now())
        ]);

        $this->loadEntry();
        // $this->notifyManager($this->entry, 'clock_out');
    }

    private function notifyManager(TimeEntry $timeEntry, string $action)
    {
        $user = auth()->user();

        // Get the team owner (manager) from the user's current team
        $currentTeam = $user->currentTeam;

        if ($currentTeam && $currentTeam->user_id !== $user->id) {
            // Notify the team owner if the user is not the owner
            $manager = User::find($currentTeam->user_id);
            if ($manager) {
                $manager->notify(new TimeTrackerNotification($user, $timeEntry, $action));
            }
        }
    }

    public function render()
    {
        return view('livewire.time-tracker');
    }
}
