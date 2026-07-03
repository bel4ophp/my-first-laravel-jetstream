<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveApproverResolver;

class LeaveRequestPolicy
{
    public function __construct(private LeaveApproverResolver $approverResolver) {}

    /**
     * Any authenticated team member can view their own leave requests list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Own requests are always visible; managers/admins can view team requests.
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->id === $leaveRequest->user_id
            || $user->hasTeamPermission($user->currentTeam, 'approve-leave-requests');
    }

    /**
     * Employees and managers can submit their own leave requests.
     */
    public function create(User $user): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'create-leave-requests');
    }

    /**
     * Managers and the admin can view the approvals queue.
     */
    public function viewApprovals(User $user): bool
    {
        return $user->hasTeamPermission($user->currentTeam, 'approve-leave-requests');
    }

    /**
     * Only the creator can cancel, and only while the request is still pending.
     */
    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->id === $leaveRequest->user_id
            && $leaveRequest->isPending();
    }

    /**
     * Only the request's designated approver may approve it, while pending.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $leaveRequest->isPending() || $user->id === $leaveRequest->user_id) {
            return false;
        }

        if (! $user->hasTeamPermission($user->currentTeam, 'approve-leave-requests')) {
            return false;
        }

        return $this->approverResolver->resolve($leaveRequest->user)?->is($user) ?? false;
    }

    /**
     * Denial follows the same rules as approval.
     */
    public function deny(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->approve($user, $leaveRequest);
    }
}