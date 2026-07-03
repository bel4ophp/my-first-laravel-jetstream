<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestStatusChanged;
use App\Notifications\LeaveRequestSubmitted;
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
     * Resolve who must approve a request submitted by the given user.
     */
    public function resolveApprover(User $submitter): ?User
    {
        return $this->approverResolver->resolve($submitter);
    }

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

        $this->resolveApprover($user)?->notify(new LeaveRequestSubmitted($leaveRequest));

        return $leaveRequest;
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

        if ($approver->is_admin) {
            // The admin approves requests submitted by managers.
            return $query->whereHas('user.teams', function ($q) {
                $q->where('team_user.role', 'manager');
            })->get();
        }

        // A manager approves employees on their own team.
        $teamId = $approver->currentTeam->id;

        return $query->whereHas('user.teams', function ($q) use ($teamId) {
            $q->where('teams.id', $teamId)->where('team_user.role', 'employee');
        })->get();
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