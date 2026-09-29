<?php

namespace App\Events;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Handled in-process by UserClockedInListener, which sends the notifications.
 * Not broadcast — the scaffolded broadcastOn() pointed at a placeholder
 * channel and the class never implemented ShouldBroadcast, so it was dead.
 */
class UserClockedInEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public TimeEntry $timeEntry,
    ) {}
}
