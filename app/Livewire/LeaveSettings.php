<?php

namespace App\Livewire;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\LeaveRecalculationService;
use App\Services\LeaveResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class LeaveSettings extends Component
{
    public ?int $teamId = null;

    // ── Holiday form state ────────────────────────────────────────────────────
    public ?int $editingHolidayId = null;
    public string $holidayName = '';
    public string $holidayDate = '';

    // ── Member days-off edit state ───────────────────────────────────────────
    public ?int $editingMemberId = null;
    public ?int $memberTotalDays = null;

    protected LeaveResetService $resetService;
    protected LeaveBalanceService $balances;
    protected LeaveRecalculationService $recalculator;

    public function boot(
        LeaveResetService $resetService,
        LeaveBalanceService $balances,
        LeaveRecalculationService $recalculator,
    ): void {
        $this->resetService = $resetService;
        $this->balances = $balances;
        $this->recalculator = $recalculator;
    }

    public function mount(): void
    {
        $this->authorize('manageSettings', LeaveRequest::class);

        $this->teamId = Auth::user()->is_admin
            ? Team::query()->orderBy('name')->value('id')
            : Auth::user()->currentTeam->id;
    }

    /**
     * Teams whose holidays the current user may manage. The admin owns every
     * team, so they get a picker; a manager is fixed to their own team.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams(): Collection
    {
        return Auth::user()->is_admin
            ? Team::orderBy('name')->get()
            : collect([Auth::user()->currentTeam]);
    }

    #[Computed]
    public function selectedTeam(): Team
    {
        // Guards against a manager tampering with teamId to reach another team.
        abort_unless($this->teams->contains('id', $this->teamId), 403);

        return $this->teams->firstWhere('id', $this->teamId);
    }

    /**
     * @return Collection<int, Holiday>
     */
    #[Computed]
    public function holidays(): Collection
    {
        return $this->selectedTeam->holidays()->orderBy('date')->get();
    }

    /**
     * Members whose pool the reset button will affect.
     *
     * @return Collection<int, \App\Models\User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->resetService->scopedUsers(Auth::user());
    }

    // ── Holidays CRUD ─────────────────────────────────────────────────────────

    public function saveHoliday(): void
    {
        $team = $this->selectedTeam;

        $data = $this->validate([
            'holidayName' => ['required', 'string', 'max:255'],
            'holidayDate' => [
                'required',
                'date',
                Rule::unique('holidays', 'date')
                    ->where('team_id', $team->id)
                    ->ignore($this->editingHolidayId),
            ],
        ]);

        // On an edit the holiday may be moving, so both the old and the new
        // date can affect existing requests.
        $affectedDates = [];

        if ($this->editingHolidayId) {
            $affectedDates[] = $team->holidays()
                ->findOrFail($this->editingHolidayId)
                ->date->toDateString();
        }

        Holiday::updateOrCreate(
            ['id' => $this->editingHolidayId],
            [
                'team_id' => $team->id,
                'name' => $data['holidayName'],
                'date' => $data['holidayDate'],
            ],
        );

        $affectedDates[] = Carbon::parse($data['holidayDate'])->toDateString();

        $this->resetHolidayForm();
        $this->afterHolidayChange($team, $affectedDates, 'Holiday saved.');
    }

    public function editHoliday(int $holidayId): void
    {
        $holiday = $this->selectedTeam->holidays()->findOrFail($holidayId);

        $this->editingHolidayId = $holiday->id;
        $this->holidayName = $holiday->name;
        $this->holidayDate = $holiday->date->toDateString();
    }

    public function deleteHoliday(int $holidayId): void
    {
        $team = $this->selectedTeam;
        $holiday = $team->holidays()->findOrFail($holidayId);
        $affectedDate = $holiday->date->toDateString();

        $holiday->delete();

        $this->resetHolidayForm();
        $this->afterHolidayChange($team, [$affectedDate], 'Holiday deleted.');
    }

    public function resetHolidayForm(): void
    {
        $this->reset(['editingHolidayId', 'holidayName', 'holidayDate']);
        $this->resetValidation();
    }

    /**
     * Holidays feed the working-day count, so any change re-derives the day
     * count on requests that span the affected dates.
     *
     * @param  array<int, string>  $affectedDates
     */
    private function afterHolidayChange(Team $team, array $affectedDates, string $message): void
    {
        $changed = $this->recalculator->recalculateForTeam($team, $affectedDates);

        unset($this->holidays, $this->members);

        if ($changed > 0) {
            $message .= " {$changed} existing request(s) recalculated.";
        }

        session()->flash('leave-success', $message);
    }

    // ── Per-member days off ───────────────────────────────────────────────────

    public function editMember(int $userId): void
    {
        $this->guardMemberInScope($userId);

        $this->editingMemberId = $userId;
        $this->memberTotalDays = $this->balances->currentBalance(User::findOrFail($userId))->total_days;
        $this->resetValidation();
    }

    public function saveMember(): void
    {
        $this->guardMemberInScope($this->editingMemberId);

        $data = $this->validate([
            'memberTotalDays' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $member = User::findOrFail($this->editingMemberId);

        $this->balances->currentBalance($member)->update(['total_days' => $data['memberTotalDays']]);

        $this->cancelMemberEdit();
        unset($this->members);

        session()->flash('leave-success', "Updated available days for {$member->name}.");
    }

    public function cancelMemberEdit(): void
    {
        $this->reset(['editingMemberId', 'memberTotalDays']);
        $this->resetValidation();
    }

    /**
     * A manager may only touch their own team's members; the admin, anyone.
     */
    private function guardMemberInScope(?int $userId): void
    {
        abort_unless(
            $userId !== null && $this->resetService->scopedUserIds(Auth::user())->contains($userId),
            403,
        );
    }

    // ── Pool reset ────────────────────────────────────────────────────────────

    public function resetBalances(): void
    {
        $this->authorize('manageSettings', LeaveRequest::class);

        $count = $this->resetService->reset(Auth::user());

        unset($this->members);

        session()->flash('leave-success', "Available days reset for {$count} member(s).");
    }

    public function render(): View
    {
        return view('livewire.leave-settings');
    }
}