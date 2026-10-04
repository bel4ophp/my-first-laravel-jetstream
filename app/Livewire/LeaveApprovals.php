<?php

namespace App\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class LeaveApprovals extends Component
{
    protected LeaveRequestService $leaveRequests;

    public function boot(LeaveRequestService $leaveRequests): void
    {
        $this->leaveRequests = $leaveRequests;
    }

    /**
     * Pending requests awaiting the current user's decision.
     *
     * @return Collection<int, LeaveRequest>
     */
    #[Computed]
    public function pendingRequests(): Collection
    {
        return $this->leaveRequests->pendingForApprover(Auth::user());
    }

    public function approve(LeaveRequest $leaveRequest): void
    {
        $this->authorize('approve', $leaveRequest);

        try {
            $this->leaveRequests->approve($leaveRequest, Auth::user());
        } catch (BusinessRuleException $e) {
            unset($this->pendingRequests);
            session()->flash('leave-error', $e->getMessage());

            return;
        }

        unset($this->pendingRequests);
        session()->flash('leave-success', __('Leave request approved.'));
    }

    public function deny(LeaveRequest $leaveRequest): void
    {
        $this->authorize('deny', $leaveRequest);

        try {
            $this->leaveRequests->deny($leaveRequest, Auth::user());
        } catch (BusinessRuleException $e) {
            unset($this->pendingRequests);
            session()->flash('leave-error', $e->getMessage());

            return;
        }

        unset($this->pendingRequests);
        session()->flash('leave-success', __('Leave request denied.'));
    }

    public function render(): View
    {
        return view('livewire.leave-approvals');
    }
}
