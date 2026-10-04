<?php

namespace App\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
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

    public function cancel(LeaveRequest $leaveRequest, LeaveRequestService $leaveRequests): void
    {
        $this->authorize('cancel', $leaveRequest);

        try {
            $leaveRequests->cancel($leaveRequest);
        } catch (BusinessRuleException $e) {
            unset($this->requests);
            session()->flash('leave-error', $e->getMessage());

            return;
        }

        unset($this->requests);
        session()->flash('leave-success', __('Your leave request has been cancelled.'));
    }

    public function render(): View
    {
        return view('livewire.leave-request-list');
    }
}
