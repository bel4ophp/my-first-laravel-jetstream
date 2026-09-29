<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AttendanceCalendarService
{
    /**
     * Returns the user IDs that $user is permitted to see entries for.
     *
     * The single definition of attendance visibility: the calendar and the CSV
     * export both read from here, so they can never disagree about scope.
     */
    public function scopedUserIds(User $user, ?int $filterUserId): Collection
    {
        $visible = $this->visibleUserIds($user);

        // Narrowing to one employee may only pick from what is already visible.
        if ($filterUserId) {
            return $visible->contains($filterUserId) ? collect([$filterUserId]) : collect();
        }

        return $visible;
    }

    /**
     * Everyone whose entries $user may see, before any per-employee filter.
     */
    private function visibleUserIds(User $user): Collection
    {
        if ($user->is_admin) {
            return User::pluck('id');
        }

        // A team owner sees their own teams — not every team in the system.
        // Members and owners are eager loaded so memberIds() stays the single
        // definition of team membership without costing two queries per team.
        $ownedTeams = $user->ownedTeams()->with(['users', 'owner'])->get();

        if ($ownedTeams->isNotEmpty()) {
            return $ownedTeams
                ->flatMap(fn (Team $team) => $team->memberIds())
                ->push($user->id)
                ->unique()
                ->values();
        }

        if ($user->currentTeam && Gate::check('viewAny', TimeEntry::class)) {
            return $user->currentTeam->memberIds();
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
        $date = $entry->work_day->toDateString();
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