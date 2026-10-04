<?php

namespace App\Livewire;

use App\Enums\LeaveType;
use App\Exceptions\InsufficientLeaveDays;
use App\Exceptions\NoWorkingDaysInRange;
use App\Exceptions\OverlappingLeaveRequest;
use App\Models\LeaveRequest;
use App\Services\LeaveBalanceService;
use App\Services\LeaveDayCalculator;
use App\Services\LeaveRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class LeaveRequestForm extends Component
{
    public string $type = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $notes = '';

    protected LeaveDayCalculator $calculator;

    protected LeaveBalanceService $balances;

    protected LeaveRequestService $leaveRequests;

    public function boot(
        LeaveDayCalculator $calculator,
        LeaveBalanceService $balances,
        LeaveRequestService $leaveRequests,
    ): void {
        $this->calculator = $calculator;
        $this->balances = $balances;
        $this->leaveRequests = $leaveRequests;
    }

    /**
     * Leave types a user may submit. Sick leave is deferred for now.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function availableTypes(): array
    {
        return collect(LeaveType::submittable())
            ->mapWithKeys(fn (LeaveType $type) => [$type->value => $type->label()])
            ->all();
    }

    #[Computed]
    public function remainingDays(): int
    {
        return $this->balances->remainingDays(Auth::user());
    }

    /**
     * Live working-day count for the chosen range, or null when not computable.
     */
    #[Computed]
    public function previewDays(): ?int
    {
        $team = Auth::user()->currentTeam;

        // Holidays are per team, so there is no working-day count without one.
        if (! $team || $this->startDate === '' || $this->endDate === '') {
            return null;
        }

        try {
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);
        } catch (\Throwable) {
            return null;
        }

        if ($end->lt($start)) {
            return null;
        }

        return $this->calculator->workingDays($team, $start, $end);
    }

    public function submit(): void
    {
        // Checked before authorizing: without a team the policy would answer
        // with a bare 403, and the person deserves to know why.
        if (! Auth::user()->currentTeam) {
            $this->addError('type', __('You need to belong to a team before you can request leave.'));

            return;
        }

        $this->authorize('create', LeaveRequest::class);

        $validated = $this->validate([
            'type' => ['required', Rule::in(array_keys($this->availableTypes()))],
            'startDate' => ['required', 'date', 'after_or_equal:today'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->leaveRequests->submit(
                Auth::user(),
                LeaveType::from($validated['type']),
                Carbon::parse($validated['startDate']),
                Carbon::parse($validated['endDate']),
                $validated['notes'] ?: null,
            );
        } catch (NoWorkingDaysInRange|OverlappingLeaveRequest $e) {
            $this->addError('startDate', $e->getMessage());

            return;
        } catch (InsufficientLeaveDays $e) {
            $this->addError('type', $e->getMessage());

            return;
        }

        $this->reset(['type', 'startDate', 'endDate', 'notes']);

        session()->flash('leave-success', __('Your leave request has been submitted.'));
        $this->dispatch('leave-request-submitted');
    }

    public function render(): View
    {
        return view('livewire.leave-request-form');
    }
}
