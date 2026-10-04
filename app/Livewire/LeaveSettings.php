<?php

namespace App\Livewire;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\HolidayService;
use App\Services\LeaveBalanceService;
use App\Services\LeaveResetService;
use Illuminate\Contracts\View\View;
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

    protected HolidayService $holidayService;

    public function boot(
        LeaveResetService $resetService,
        LeaveBalanceService $balances,
        HolidayService $holidayService,
    ): void {
        $this->resetService = $resetService;
        $this->balances = $balances;
        $this->holidayService = $holidayService;
    }

    /**
     * Runs before every action on an already-open page. mount() only runs on
     * the first load, so without this a manager demoted mid-session could keep
     * editing holidays and days off from a tab they left open.
     */
    public function hydrate(): void
    {
        $this->authorize('manageSettings', LeaveRequest::class);
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

    /**
     * teamId comes from the browser, so the policy decides whether this user
     * may manage the team it names.
     */
    #[Computed]
    public function selectedTeam(): Team
    {
        $team = Team::findOrFail($this->teamId);

        $this->authorize('manageLeaveSettings', $team);

        return $team;
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
     * @return Collection<int, User>
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

        $changed = $this->holidayService->save(
            $team,
            $data['holidayName'],
            $data['holidayDate'],
            $this->editingHolidayId ? $team->holidays()->findOrFail($this->editingHolidayId) : null,
        );

        $this->resetHolidayForm();
        $this->afterHolidayChange($changed, __('Holiday saved.'));
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
        $changed = $this->holidayService->delete($this->selectedTeam->holidays()->findOrFail($holidayId));

        $this->resetHolidayForm();
        $this->afterHolidayChange($changed, __('Holiday deleted.'));
    }

    public function resetHolidayForm(): void
    {
        $this->reset(['editingHolidayId', 'holidayName', 'holidayDate']);
        $this->resetValidation();
    }

    /**
     * @param  int  $changed  existing requests HolidayService recalculated
     */
    private function afterHolidayChange(int $changed, string $message): void
    {
        unset($this->holidays, $this->members);

        if ($changed > 0) {
            $message .= ' '.__(':count existing request(s) recalculated.', ['count' => $changed]);
        }

        session()->flash('leave-success', $message);
    }

    // ── Per-member days off ───────────────────────────────────────────────────

    public function editMember(int $userId): void
    {
        $member = User::findOrFail($userId);
        $this->authorize('manageLeaveBalance', $member);

        $this->editingMemberId = $member->id;
        $this->memberTotalDays = $this->balances->currentBalance($member)->total_days;
        $this->resetValidation();
    }

    public function saveMember(): void
    {
        $member = User::findOrFail($this->editingMemberId);
        $this->authorize('manageLeaveBalance', $member);

        $data = $this->validate([
            'memberTotalDays' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $this->balances->setTotalDays($member, $data['memberTotalDays']);

        $this->cancelMemberEdit();
        unset($this->members);

        session()->flash('leave-success', __('Updated available days for :name.', ['name' => $member->name]));
    }

    public function cancelMemberEdit(): void
    {
        $this->reset(['editingMemberId', 'memberTotalDays']);
        $this->resetValidation();
    }

    // ── Pool reset ────────────────────────────────────────────────────────────

    public function resetBalances(): void
    {
        $this->authorize('manageSettings', LeaveRequest::class);

        $count = $this->resetService->reset(Auth::user());

        unset($this->members);

        session()->flash('leave-success', __('Available days reset for :count member(s).', ['count' => $count]));
    }

    public function render(): View
    {
        return view('livewire.leave-settings');
    }
}
