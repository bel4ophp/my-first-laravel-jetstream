<?php

namespace App\Livewire;

use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
        } catch (ValidationException $e) {
            session()->flash('leave-error', $e->getMessage());

            return;
        }

        unset($this->pendingRequests);
        session()->flash('leave-success', 'Leave request approved.');
    }

    public function deny(LeaveRequest $leaveRequest): void
    {
        $this->authorize('deny', $leaveRequest);

        $this->leaveRequests->deny($leaveRequest, Auth::user());

        unset($this->pendingRequests);
        session()->flash('leave-success', 'Leave request denied.');
    }

    public function render(): View
    {
        return view('livewire.leave-approvals');
    }
}