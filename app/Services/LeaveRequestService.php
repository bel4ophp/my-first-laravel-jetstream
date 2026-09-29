<?php

namespace App\Services;

use App\Enums\TeamRole;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestStatusChanged;
use App\Notifications\LeaveRequestSubmitted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
     * @throws ValidationException when the range yields no working days
     *                             or the pool cannot cover the request.
     */
    public function submit(User $user, LeaveType $type, Carbon $start, Carbon $end, ?string $notes = null): LeaveRequest
    {
        $days = $this->calculator->workingDays($user->currentTeam, $start, $end);

        if ($days < 1) {
            throw ValidationException::withMessages([
                'startDate' => 'The selected range contains no working days (only weekends/holidays).',
            ]);
        }

        if ($this->hasOverlappingRequest($user, $start, $end)) {
            throw ValidationException::withMessages([
                'startDate' => 'You already have a leave request covering part of this range.',
            ]);
        }

        if (! $this->balances->hasSufficientDays($user, $type, $days, $start->year)) {
            throw ValidationException::withMessages([
                'type' => "Insufficient leave days. You have {$this->balances->remainingDays($user, $start->year)} day(s) remaining.",
            ]);
        }

        $leaveRequest = $user->leaveRequests()->create([
            'type' => $type,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'calculated_days' => $days,
            'status' => LeaveStatus::Pending,
            'notes' => $notes,
        ]);

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
     * @throws ValidationException when the pool can no longer cover the request.
     */
    public function approve(LeaveRequest $leaveRequest, User $approver): LeaveRequest
    {
        $year = $leaveRequest->start_date->year;

        if ($leaveRequest->type->deductsFromPool()
            && ! $this->balances->hasSufficientDays($leaveRequest->user, $leaveRequest->type, $leaveRequest->calculated_days, $year)
        ) {
            throw ValidationException::withMessages([
                'approval' => "Cannot approve: {$leaveRequest->user->name} no longer has enough days in the pool.",
            ]);
        }

        if ($leaveRequest->type->deductsFromPool()) {
            $this->balances->deduct($leaveRequest->user, $leaveRequest->calculated_days, $year);
        }

        $leaveRequest->update([
            'status' => LeaveStatus::Approved,
            'approved_by' => $approver->id,
        ]);

        $leaveRequest->user->notify(new LeaveRequestStatusChanged($leaveRequest));

        return $leaveRequest;
    }

    /**
     * Deny a pending request and notify the submitter. No balance is touched
     * because pool days are only deducted on approval.
     */
    public function deny(LeaveRequest $leaveRequest, User $approver): LeaveRequest
    {
        $leaveRequest->update([
            'status' => LeaveStatus::Denied,
            'approved_by' => $approver->id,
        ]);

        $leaveRequest->user->notify(new LeaveRequestStatusChanged($leaveRequest));

        return $leaveRequest;
    }
}