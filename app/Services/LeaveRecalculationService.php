<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class LeaveRecalculationService
{
    public function __construct(
        private LeaveDayCalculator $calculator,
        private LeaveBalanceService $balances,
    ) {}

    /**
     * Re-derive `calculated_days` for the team's open requests that span any of
     * the given dates, after that team's holidays changed.
     *
     * Only pending and approved requests are touched — denied and cancelled
     * ones are terminal. For approved requests that draw from the pool, the
     * balance is adjusted by the difference so the pool stays truthful.
     *
     * @param  array<int, string>  $dates  affected dates (Y-m-d)
     * @return int  the number of requests whose day count changed
     */
    public function recalculateForTeam(Team $team, array $dates): int
    {
        $dates = array_values(array_unique($dates));

        if ($dates === []) {
            return 0;
        }

        return DB::transaction(function () use ($team, $dates) {
            $requests = LeaveRequest::query()
                ->whereIn('user_id', $team->users()->pluck('users.id'))
                ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
                ->where(function ($query) use ($dates) {
                    foreach ($dates as $date) {
                        $query->orWhere(function ($inner) use ($date) {
                            $inner->where('start_date', '<=', $date)
                                ->where('end_date', '>=', $date);
                        });
                    }
                })
                ->with('user')
                ->get();

            $changed = 0;

            foreach ($requests as $request) {
                $newDays = $this->calculator->workingDays(
                    $team,
                    $request->start_date,
                    $request->end_date,
                );

                if ($newDays === $request->calculated_days) {
                    continue;
                }

                $this->adjustBalance($request, $newDays);

                $request->update(['calculated_days' => $newDays]);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Approved pool-drawing requests already moved days out of the balance, so
     * the delta has to be applied there too. Everything else only changes its
     * day count.
     */
    private function adjustBalance(LeaveRequest $request, int $newDays): void
    {
        if ($request->status !== LeaveStatus::Approved || ! $request->type->deductsFromPool()) {
            return;
        }

        $delta = $newDays - $request->calculated_days;
        $year = $request->start_date->year;

        if ($delta > 0) {
            $this->balances->deduct($request->user, $delta, $year);

            return;
        }

        $this->balances->restore($request->user, abs($delta), $year);
    }
}