<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestCancelled extends Notification implements ShouldQueue
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
            ->subject('Leave Request Cancelled')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$employee->name} cancelled their {$this->leaveRequest->type->label()} request for {$this->leaveRequest->start_date->toFormattedDateString()} – {$this->leaveRequest->end_date->toFormattedDateString()}.")
            ->action('View Requests', route('leave.index', ['tab' => 'validation']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'leave_request_cancelled',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_id' => $this->leaveRequest->user_id,
            'employee_name' => $this->leaveRequest->user->name,
            'leave_type' => $this->leaveRequest->type->value,
            'start_date' => $this->leaveRequest->start_date->toDateString(),
            'end_date' => $this->leaveRequest->end_date->toDateString(),
        ];
    }
}