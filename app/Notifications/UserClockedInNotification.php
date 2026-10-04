<?php

namespace App\Notifications;

use App\Enums\ClockAction;
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
        public ClockAction $action,
    ) {}

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
        $workDay = $this->timeEntry->work_day;

        return (new MailMessage)
            ->subject($this->summary())
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->summary()} on {$workDay->toFormattedDateString()}.")
            ->action('View Attendance', route('reports.attendance.index', [
                'year' => $workDay->year,
                'month' => $workDay->month,
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'time_tracker',
            'action' => $this->action->value,
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'time_entry_id' => $this->timeEntry->id,
        ];
    }

    /**
     * One line describing what happened, shared by the subject and the body so
     * the two can't drift apart.
     */
    private function summary(): string
    {
        return match ($this->action) {
            ClockAction::ClockIn => "{$this->employee->name} clocked in at {$this->timeEntry->clockInFormatted()}",
            ClockAction::ClockOut => "{$this->employee->name} clocked out at ".($this->timeEntry->clockOutFormatted() ?? '—'),
        };
    }
}
