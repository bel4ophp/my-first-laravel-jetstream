<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Carbon;

class LeaveDayCalculator
{
    /**
     * Count the deductible working days in an inclusive date range,
     * excluding weekends and the team's holidays.
     */
    public function workingDays(Team $team, Carbon $start, Carbon $end): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        $holidayDates = $this->teamHolidayDates($team, $start, $end);

        $count = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            if (in_array($day->toDateString(), $holidayDates, true)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, string> The team's holiday dates (Y-m-d) within the range.
     */
    private function teamHolidayDates(Team $team, Carbon $start, Carbon $end): array
    {
        return $team->holidays()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn ($holiday) => $holiday->date->toDateString())
            ->all();
    }
}