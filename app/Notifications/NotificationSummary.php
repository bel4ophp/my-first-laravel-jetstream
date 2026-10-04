<?php

namespace App\Notifications;

use App\Enums\ClockAction;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns a stored database notification into a headline and a line of text.
 *
 * Built at display time from the stored data rather than saved with it, so rows
 * already in the database read correctly too. Each notification class's
 * toDatabase() sets the 'type' key this dispatches on.
 */
class NotificationSummary
{
    /**
     * @return array{title: string, body: string}
     */
    public static function for(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return match ($data['type'] ?? null) {
            'time_tracker' => [
                'title' => $data['employee_name'] ?? 'A team member',
                'body' => ClockAction::tryFrom($data['action'] ?? '') === ClockAction::ClockOut ? 'Clocked out' : 'Clocked in',
            ],
            'leave_request_submitted' => [
                'title' => $data['employee_name'] ?? 'A team member',
                'body' => 'Requested '.self::leave($data).' ('.self::days($data).')',
            ],
            'leave_request_status_changed' => [
                'title' => 'Your leave request',
                'body' => self::leave($data).' was '.self::status($data),
            ],
            'leave_request_cancelled' => [
                'title' => $data['employee_name'] ?? 'A team member',
                'body' => 'Cancelled '.self::leave($data),
            ],
            default => [
                'title' => 'Notification',
                'body' => '',
            ],
        };
    }

    /**
     * "Annual Leave, Jun 15, 2026 – Jun 19, 2026", or a single date for one day.
     *
     * @param  array<string, mixed>  $data
     */
    private static function leave(array $data): string
    {
        $type = LeaveType::tryFrom($data['leave_type'] ?? '')?->label() ?? 'Leave';
        $start = Carbon::parse($data['start_date'])->toFormattedDateString();
        $end = Carbon::parse($data['end_date'])->toFormattedDateString();

        return $start === $end ? "{$type}, {$start}" : "{$type}, {$start} – {$end}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function days(array $data): string
    {
        $days = (int) ($data['calculated_days'] ?? 0);

        return $days.' '.Str::plural('day', $days);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function status(array $data): string
    {
        return Str::lower(LeaveStatus::tryFrom($data['status'] ?? '')?->label() ?? 'updated');
    }
}
