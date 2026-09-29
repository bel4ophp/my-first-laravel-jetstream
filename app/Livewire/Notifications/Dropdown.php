<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Dropdown extends Component
{
    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        return Auth::user()
            ->notifications()
            ->latest()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function markAsRead(string $id): void
    {
        Auth::user()->notifications()->where('id', $id)->first()?->markAsRead();

        unset($this->notifications, $this->unreadCount);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();

        unset($this->notifications, $this->unreadCount);
    }

    public function render(): View
    {
        return view('livewire.notifications.dropdown');
    }
}
