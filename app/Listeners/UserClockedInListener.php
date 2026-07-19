<?php

namespace App\Listeners;

use App\Events\UserClockedInEvent;
use App\Models\User;
use App\Notifications\UserClockedInNotification;
use Illuminate\Support\Facades\Notification;

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
        $managerClockedIn = $manager && $manager->getKey() === $user->getKey();

        // Skip notifying the manager about their own clock-in.
        if ($manager && ! $managerClockedIn) {
            $manager->notify(
                new UserClockedInNotification(
                    $user,
                    $event->timeEntry,
                    'clock_in'
                )
            );
        }

        // When the manager clocks in, their team's employees are notified
        // in-app and by mail.
        if ($managerClockedIn) {
            $employees = $currentTeam->users()
                ->wherePivot('role', 'employee')
                ->whereKeyNot($user->getKey())
                ->get();

            Notification::send(
                $employees,
                new UserClockedInNotification(
                    $user,
                    $event->timeEntry,
                    'clock_in'
                )
            );
        }

        // Global admins receive an in-app-only notification for every other
        // user's clock-in. The clocking-in user and the team manager (who was
        // already notified above) are excluded to avoid duplicate copies.
        $admins = User::query()
            ->where('is_admin', true)
            ->whereKeyNot(array_filter([$user->getKey(), $manager?->getKey()]))
            ->get();

        Notification::send(
            $admins,
            new UserClockedInNotification(
                $user,
                $event->timeEntry,
                'clock_in'
            )
        );
    }
}
