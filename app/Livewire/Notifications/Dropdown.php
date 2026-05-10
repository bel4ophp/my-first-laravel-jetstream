<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class Dropdown extends Component
{
    public function getNotificationsProperty()
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->limit(10)
            ->get();
    }

    public function getUnreadCountProperty()
    {
        return auth()->user()
            ->unreadNotifications()
            ->count();
    }

    public function markAsRead(string $id): void
    {
        $notification = auth()->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()
            ->unreadNotifications
            ->markAsRead();
    }

    public function render()
    {
        return view('livewire.notifications.dropdown');
    }
}
