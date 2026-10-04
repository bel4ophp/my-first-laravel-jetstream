<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A team's public holidays.
 *
 * Holidays feed the working-day count, so every change also re-derives the day
 * count on the team's open requests that span the affected dates — in the same
 * transaction, so a holiday never changes while those requests keep stale
 * counts. Callers authorize first (TeamPolicy::manageLeaveSettings) and pass a
 * holiday already scoped to the team.
 */
class HolidayService
{
    public function __construct(private LeaveRecalculationService $recalculator) {}

    /**
     * Create a holiday, or update $holiday when given.
     *
     * @return int the number of existing requests whose day count changed
     */
    public function save(Team $team, string $name, string $date, ?Holiday $holiday = null): int
    {
        return DB::transaction(function () use ($team, $name, $date, $holiday) {
            // An edit may move the holiday, so both its old and new date matter.
            $affectedDates = $holiday ? [$holiday->date->toDateString()] : [];

            $attributes = ['name' => $name, 'date' => Carbon::parse($date)->toDateString()];

            $holiday
                ? $holiday->update($attributes)
                : $team->holidays()->create($attributes);

            $affectedDates[] = $attributes['date'];

            return $this->recalculator->recalculateForTeam($team, $affectedDates);
        });
    }

    /**
     * @return int the number of existing requests whose day count changed
     */
    public function delete(Holiday $holiday): int
    {
        return DB::transaction(function () use ($holiday) {
            $affectedDate = $holiday->date->toDateString();

            $holiday->delete();

            return $this->recalculator->recalculateForTeam($holiday->team, [$affectedDate]);
        });
    }
}
