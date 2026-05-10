<?php

namespace App\Listeners;

use App\Events\UserClockedInEvent;
use App\Models\User;
use App\Notifications\UserClockedInNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UserClockedInListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserClockedInEvent $event): void
    {
        $user = $event->user;
        $currentTeam = $user->currentTeam;
        if (!$currentTeam) {
            return;
        }

        $manager = User::getTeamManager($currentTeam->id);

        Log::warning("Manager ");
        Log::warning($manager);

        if (!$manager) {
            Log::warning("No manager found for team ID {$currentTeam->id} when user clocked in.");
            return;
        }

        $manager->notify(
            new UserClockedInNotification(
                $user,
                $event->timeEntry,
                'clock_in'
            )
        );
    }
}
