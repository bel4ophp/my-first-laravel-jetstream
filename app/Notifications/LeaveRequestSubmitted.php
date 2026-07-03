<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->leaveRequest->user;

        return (new MailMessage)
            ->subject('New Leave Request Pending Approval')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$employee->name} submitted a {$this->leaveRequest->type->label()} request.")
            ->line("Dates: {$this->leaveRequest->start_date->toFormattedDateString()} – {$this->leaveRequest->end_date->toFormattedDateString()} ({$this->leaveRequest->calculated_days} day(s)).")
            ->action('Review Request', route('leave.index', ['tab' => 'validation']))
            ->line('Please review it at your earliest convenience.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'leave_request_submitted',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_id' => $this->leaveRequest->user_id,
            'employee_name' => $this->leaveRequest->user->name,
            'leave_type' => $this->leaveRequest->type->value,
            'start_date' => $this->leaveRequest->start_date->toDateString(),
            'end_date' => $this->leaveRequest->end_date->toDateString(),
            'calculated_days' => $this->leaveRequest->calculated_days,
        ];
    }
}