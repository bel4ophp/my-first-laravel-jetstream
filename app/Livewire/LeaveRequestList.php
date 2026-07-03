<?php

namespace App\Livewire;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestCancelled;
use App\Services\LeaveApproverResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class LeaveRequestList extends Component
{
    /**
     * The current user's leave requests, newest first.
     *
     * @return Collection<int, LeaveRequest>
     */
    #[Computed]
    public function requests(): Collection
    {
        return Auth::user()
            ->leaveRequests()
            ->latest()
            ->get();
    }

    #[On('leave-request-submitted')]
    public function refresh(): void
    {
        unset($this->requests);
    }

    public function cancel(LeaveRequest $leaveRequest, LeaveApproverResolver $approverResolver): void
    {
        $this->authorize('cancel', $leaveRequest);

        $leaveRequest->update([
            'status' => LeaveStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $approverResolver->resolve($leaveRequest->user)
            ?->notify(new LeaveRequestCancelled($leaveRequest));

        unset($this->requests);
        session()->flash('leave-success', 'Your leave request has been cancelled.');
    }

    public function render(): View
    {
        return view('livewire.leave-request-list');
    }
}