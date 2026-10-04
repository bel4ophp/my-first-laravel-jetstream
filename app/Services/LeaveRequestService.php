<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\TeamRole;
use App\Exceptions\InsufficientLeaveDays;
use App\Exceptions\LeaveRequestAlreadyDecided;
use App\Exceptions\NoWorkingDaysInRange;
use App\Exceptions\OverlappingLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestCancelled;
use App\Notifications\LeaveRequestStatusChanged;
use App\Notifications\LeaveRequestSubmitted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveRequestService
{
    public function __construct(
        private LeaveDayCalculator $calculator,
        private LeaveBalanceService $balances,
        private LeaveApproverResolver $approverResolver,
    ) {}

    /**
     * Create a pending leave request and notify its approver.
     *
     * The submitter's user row is locked for the duration, so two submissions
     * from the same person run one after the other and the overlap check always
     * sees a request the other one just created.
     *
     * @throws NoWorkingDaysInRange when the range holds only weekends/holidays
     * @throws OverlappingLeaveRequest when a live request covers part of it
     * @throws InsufficientLeaveDays when the pool cannot cover it
     */
    public function submit(User $user, LeaveType $type, Carbon $start, Carbon $end, ?string $notes = null): LeaveRequest
    {
        $days = $this->calculator->workingDays($user->currentTeam, $start, $end);

        if ($days < 1) {
            throw new NoWorkingDaysInRange;
        }

        $leaveRequest = DB::transaction(function () use ($user, $type, $start, $end, $days, $notes) {
            User::whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->hasOverlappingRequest($user, $start, $end)) {
                throw new OverlappingLeaveRequest;
            }

            if (! $this->balances->hasSufficientDays($user, $type, $days, $start->year)) {
                throw InsufficientLeaveDays::toSubmit($this->balances->remainingDays($user, $start->year));
            }

            return $user->leaveRequests()->create([
                'type' => $type,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'calculated_days' => $days,
                'status' => LeaveStatus::Pending,
                'notes' => $notes,
            ]);
        });

        $this->approverResolver->resolve($user)?->notify(new LeaveRequestSubmitted($leaveRequest));

        return $leaveRequest;
    }

    /**
     * Whether the user already has a live request touching any of these dates.
     *
     * Two ranges overlap when each starts on or before the other ends. Only
     * pending and approved requests block — denied and cancelled ones are
     * terminal and release their dates.
     *
     * Without this, the same week could be requested any number of times: the
     * pool is only debited at approval, so every duplicate passed the balance
     * check and the over-draw surfaced later as an approval that failed.
     */
    private function hasOverlappingRequest(User $user, Carbon $start, Carbon $end): bool
    {
        return $user->leaveRequests()
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
    }

    /**
     * Pending requests the given user is responsible for approving.
     *
     * @return Collection<int, LeaveRequest>
     */
    public function pendingForApprover(User $approver): Collection
    {
        $query = LeaveRequest::with('user')
            ->where('status', LeaveStatus::Pending)
            ->latest();

        // The admin approves requests submitted by managers, on any team.
        if ($approver->is_admin) {
            return $query->whereIn('user_id', $this->submittersActingAs(TeamRole::Manager->value))->get();
        }

        $team = $approver->currentTeam;

        // A team's single manager approves that team's employees. Anyone else
        // holding the approve permission has nothing to act on.
        if (! $approver->is($team?->manager())) {
            return collect();
        }

        return $query->whereIn('user_id', $this->submittersActingAs(TeamRole::Employee->value, $team->id))->get();
    }

    /**
     * Users whose role on their *own current team* is the given one.
     *
     * LeaveApproverResolver routes a request using the submitter's current team,
     * so the queue has to be scoped the same way. Matching on team membership
     * alone would surface requests the policy then refuses to approve.
     *
     * @return Builder<User>
     */
    private function submittersActingAs(string $role, ?int $teamId = null): Builder
    {
        return User::query()
            ->join('team_user', function (JoinClause $join) use ($role) {
                $join->on('team_user.user_id', '=', 'users.id')
                    ->on('team_user.team_id', '=', 'users.current_team_id')
                    ->where('team_user.role', $role);
            })
            ->when($teamId, fn (Builder $query) => $query->where('users.current_team_id', $teamId))
            ->select('users.id');
    }

    /**
     * Approve a pending request, deducting pool days and notifying the submitter.
     *
     * The request and the balance are both locked, so a second approval, a
     * denial or a cancellation racing this one waits and then finds the request
     * already decided; and two approvals for the same person can't both pass
     * the balance check against the same remaining days.
     *
     * @throws LeaveRequestAlreadyDecided when someone else decided it first
     * @throws InsufficientLeaveDays when the pool can no longer cover it
     */
    public function approve(LeaveRequest $leaveRequest, User $approver): LeaveRequest
    {
        $leaveRequest = DB::transaction(function () use ($leaveRequest, $approver) {
            $locked = $this->lockPending($leaveRequest);
            $year = $locked->start_date->year;

            if ($locked->type->deductsFromPool()) {
                $this->balances->lockBalance($locked->user, $year);

                if (! $this->balances->hasSufficientDays($locked->user, $locked->type, $locked->calculated_days, $year)) {
                    throw InsufficientLeaveDays::toApprove($locked->user);
                }

                $this->balances->deduct($locked->user, $locked->calculated_days, $year);
            }

            $locked->update([
                'status' => LeaveStatus::Approved,
                'approved_by' => $approver->id,
            ]);

            return $locked;
        });

        // After commit, so a rolled-back approval never tells anyone "approved".
        $leaveRequest->user->notify(new LeaveRequestStatusChanged($leaveRequest));

        return $leaveRequest;
    }

    /**
     * Deny a pending request and notify the submitter. No balance is touched
     * because pool days are only deducted on approval.
     *
     * @throws LeaveRequestAlreadyDecided when someone else decided it first
     */
    public function deny(LeaveRequest $leaveRequest, User $approver): LeaveRequest
    {
        $leaveRequest = DB::transaction(function () use ($leaveRequest, $approver) {
            $locked = $this->lockPending($leaveRequest);

            $locked->update([
                'status' => LeaveStatus::Denied,
                'approved_by' => $approver->id,
            ]);

            return $locked;
        });

        $leaveRequest->user->notify(new LeaveRequestStatusChanged($leaveRequest));

        return $leaveRequest;
    }

    /**
     * Cancel a pending request on its submitter's behalf and tell the approver.
     * No balance is touched because pool days are only deducted on approval.
     *
     * @throws LeaveRequestAlreadyDecided when someone else decided it first
     */
    public function cancel(LeaveRequest $leaveRequest): LeaveRequest
    {
        $leaveRequest = DB::transaction(function () use ($leaveRequest) {
            $locked = $this->lockPending($leaveRequest);

            $locked->update([
                'status' => LeaveStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $locked;
        });

        $this->approverResolver->resolve($leaveRequest->user)
            ?->notify(new LeaveRequestCancelled($leaveRequest));

        return $leaveRequest;
    }

    /**
     * Re-read the request under a row lock and make sure it is still pending.
     *
     * The caller's copy may be stale — loaded, and authorized, before another
     * request decided it — so the status it carries can't be trusted. Must be
     * called inside DB::transaction().
     *
     * @throws LeaveRequestAlreadyDecided when someone else decided it first
     */
    private function lockPending(LeaveRequest $leaveRequest): LeaveRequest
    {
        $locked = LeaveRequest::whereKey($leaveRequest->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw new LeaveRequestAlreadyDecided($locked->status);
        }

        return $locked;
    }
}
