<?php

namespace App\Services;

use App\Events\UserClockedInEvent;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Self-service clocking in and out: one shift per user per day.
 *
 * State is always read from the database, never from the caller — the
 * dashboard widget's properties are editable from the browser, and an API
 * client could send anything. Both writes lock the user's row, so two clicks
 * arriving together run one after the other instead of both seeing "no shift
 * yet" and creating two.
 *
 * Managers adding or editing entries in the attendance calendar go through
 * AttendanceCalendarService instead; that path may record several per day.
 */
class TimeTrackingService
{
    /**
     * The user's shift for today, or null before they clock in.
     */
    public function todaysEntry(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->onDay(today())
            ->orderBy('id')
            ->first();
    }

    /**
     * Start today's shift and notify the people who follow it.
     *
     * Refused once today's shift exists — running or finished — so a second
     * click can't move the start time and a finished shift can't be reopened.
     *
     * @return TimeEntry|null the new shift, or null when today's already started
     */
    public function clockIn(User $user): ?TimeEntry
    {
        $entry = DB::transaction(function () use ($user) {
            User::whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->todaysEntry($user)) {
                return null;
            }

            return TimeEntry::create([
                'user_id' => $user->id,
                // A "Y-m-d" string, the same form the onDay() scope compares.
                'work_day' => today()->toDateString(),
                'clock_in' => now(),
            ]);
        });

        // After commit, so nobody is notified of a shift that rolled back.
        if ($entry) {
            event(new UserClockedInEvent($user, $entry));
        }

        return $entry;
    }

    /**
     * End today's running shift, capped at the maximum shift length.
     *
     * Only a running shift can be closed, so a finished one can't have its end
     * — and its worked minutes — pushed later. The cap is the same rule the
     * scheduled close-expired-workdays command applies, so a shift is recorded
     * the same way whether the browser timer, the API or the scheduler ends it.
     *
     * @return TimeEntry|null the closed shift, or null when none was running
     */
    public function clockOut(User $user): ?TimeEntry
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->getKey())->lockForUpdate()->first();

            $entry = TimeEntry::where('user_id', $user->id)
                ->onDay(today())
                ->active()
                ->first();

            if (! $entry) {
                return null;
            }

            $clockOut = now()->min($entry->clock_in->copy()->addHours(self::maxShiftHours()));

            $entry->update([
                'clock_out' => $clockOut,
                'worked_minutes' => (int) $entry->clock_in->diffInMinutes($clockOut),
            ]);

            return $entry;
        });
    }

    /**
     * The longest a shift may run, from config/attendance.php.
     */
    public static function maxShiftHours(): int
    {
        return (int) config('attendance.max_shift_hours');
    }
}
