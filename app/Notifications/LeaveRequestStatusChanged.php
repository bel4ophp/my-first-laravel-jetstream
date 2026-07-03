<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestStatusChanged extends Notification implements ShouldQueue
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
        $status = $this->leaveRequest->status->label();

        return (new MailMessage)
            ->subject("Your Leave Request Was {$status}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$this->leaveRequest->type->label()} request for {$this->leaveRequest->start_date->toFormattedDateString()} – {$this->leaveRequest->end_date->toFormattedDateString()} was {$status}.")
            ->action('View Request', route('leave.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'leave_request_status_changed',
            'leave_request_id' => $this->leaveRequest->id,
            'leave_type' => $this->leaveRequest->type->value,
            'status' => $this->leaveRequest->status->value,
            'start_date' => $this->leaveRequest->start_date->toDateString(),
            'end_date' => $this->leaveRequest->end_date->toDateString(),
        ];
    }
}