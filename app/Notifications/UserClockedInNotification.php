<?php

namespace App\Notifications;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserClockedInNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public User $employee,
        public TimeEntry $timeEntry,
        public string $action
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * Global admins receive an in-app (database) notification only; everyone
     * else (e.g. the team manager) also receives it by mail.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && $notifiable->is_admin) {
            return ['database'];
        }

        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'time_tracker',
            'action' => $this->action,
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'time_entry_id' => $this->timeEntry->id,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
