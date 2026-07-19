<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AttendanceCalendarService
{
    /**
     * Returns the user IDs that $user is permitted to see entries for.
     */
    public function scopedUserIds(User $user, ?int $filterUserId): Collection
    {
        if ($filterUserId) {
            return User::whereKey($filterUserId)->pluck('id');
        }

        if ($user->is_admin || $user->ownsTeam($user->currentTeam)) {
            return User::pluck('id');
        }

        if ($user->currentTeam && Gate::check('viewAny', TimeEntry::class)) {
            return $user->currentTeam->allUsers()->pluck('id');
        }

        return collect([$user->id]);
    }

    /**
     * Returns the users available for selection when creating/assigning a time entry.
     */
    public function selectableUsers(User $user): Collection
    {
        if ($user->is_admin || $user->ownsTeam($user->currentTeam)) {
            return User::where('is_admin', false)->orderBy('name')->get();
        }

        if ($user->currentTeam) {
            return $user->currentTeam->allUsers()
                ->reject(fn (User $member) => $member->is_admin)
                ->sortBy('name')
                ->values();
        }

        return collect();
    }

    /**
     * @throws \InvalidArgumentException when clock_out precedes clock_in
     */
    public function createTimeEntry(
        int $userId,
        string $workDay,
        string $clockIn,
        ?string $clockOut,
    ): TimeEntry {
        $parsedClockIn  = $this->parseClock($workDay, $clockIn);
        $parsedClockOut = null;

        if ($clockOut) {
            $parsedClockOut = $this->parseClock($workDay, $clockOut);

            if ($parsedClockOut->lt($parsedClockIn)) {
                throw new \InvalidArgumentException('Clock out must be after clock in.');
            }
        }

        return TimeEntry::create([
            'user_id'        => $userId,
            'work_day'       => $workDay,
            'clock_in'       => $parsedClockIn,
            'clock_out'      => $parsedClockOut,
            'worked_minutes' => $parsedClockOut ? $parsedClockIn->diffInMinutes($parsedClockOut) : null,
        ]);
    }

    /**
     * @throws \InvalidArgumentException when clock_out precedes clock_in
     */
    public function updateTimeEntry(TimeEntry $entry, string $clockIn, ?string $clockOut): void
    {
        $date           = Carbon::parse($entry->work_day)->format('Y-m-d');
        $parsedClockIn  = $this->parseClock($date, $clockIn);
        $parsedClockOut = null;

        if ($clockOut) {
            $parsedClockOut = $this->parseClock($date, $clockOut);

            if ($parsedClockOut->lt($parsedClockIn)) {
                throw new \InvalidArgumentException('Clock out must be after clock in.');
            }
        }

        $entry->clock_in       = $parsedClockIn;
        $entry->clock_out      = $parsedClockOut;
        $entry->worked_minutes = $parsedClockOut ? $parsedClockIn->diffInMinutes($parsedClockOut) : null;
        $entry->save();
    }

    private function parseClock(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "$date $time");
    }
}