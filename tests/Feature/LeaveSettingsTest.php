<?php

namespace Tests\Feature;

use App\Livewire\LeaveSettings;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    private function makeManager(Team $team): User
    {
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager, ['role' => 'manager']);

        return $manager;
    }

    private function makeEmployee(Team $team): User
    {
        $employee = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($employee, ['role' => 'employee']);

        return $employee;
    }

    private function usedDays(User $user): int
    {
        return LeaveBalance::where('user_id', $user->id)->where('year', now()->year)->value('used_days');
    }

    // ── Pool reset ────────────────────────────────────────────────────────────

    public function test_manager_reset_covers_themselves_and_their_team_employees_only(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        $otherOwner = $this->makeOwner();
        $otherEmployee = $this->makeEmployee($otherOwner->currentTeam);

        foreach ([$manager, $employee, $otherEmployee] as $member) {
            LeaveBalance::factory()->create([
                'user_id' => $member->id,
                'year' => now()->year,
                'used_days' => 12,
            ]);
        }

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('resetBalances');

        $this->assertSame(0, $this->usedDays($manager));
        $this->assertSame(0, $this->usedDays($employee));
        $this->assertSame(12, $this->usedDays($otherEmployee));
    }

    public function test_admin_reset_covers_every_teams_managers_and_employees(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $teamA = $this->makeOwner()->currentTeam;
        $managerA = $this->makeManager($teamA);
        $employeeA = $this->makeEmployee($teamA);

        $teamB = $this->makeOwner()->currentTeam;
        $employeeB = $this->makeEmployee($teamB);

        foreach ([$managerA, $employeeA, $employeeB] as $member) {
            LeaveBalance::factory()->create([
                'user_id' => $member->id,
                'year' => now()->year,
                'used_days' => 9,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(LeaveSettings::class)
            ->call('resetBalances');

        $this->assertSame(0, $this->usedDays($managerA));
        $this->assertSame(0, $this->usedDays($employeeA));
        $this->assertSame(0, $this->usedDays($employeeB));
    }

    public function test_reset_creates_a_fresh_balance_for_members_without_one(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        $this->assertDatabaseCount('leave_balances', 0);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('resetBalances');

        $this->assertSame(0, $this->usedDays($manager));
        $this->assertSame(0, $this->usedDays($employee));
        $this->assertDatabaseCount('leave_balances', 2);
    }

    public function test_members_list_is_scoped_to_the_managers_team(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        $otherOwner = $this->makeOwner();
        $otherEmployee = $this->makeEmployee($otherOwner->currentTeam);

        $ids = Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->get('members')
            ->pluck('id');

        $this->assertTrue($ids->contains($manager->id));
        $this->assertTrue($ids->contains($employee->id));
        $this->assertFalse($ids->contains($otherEmployee->id));
    }

    public function test_employee_cannot_open_leave_settings(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($employee)
            ->test(LeaveSettings::class)
            ->assertForbidden();
    }

    // ── Holidays CRUD ─────────────────────────────────────────────────────────

    public function test_manager_can_create_a_holiday_for_their_team(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->set('holidayName', 'Founders Day')
            ->set('holidayDate', '2026-08-10')
            ->call('saveHoliday')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('holidays', [
            'team_id' => $owner->currentTeam->id,
            'name' => 'Founders Day',
        ]);
    }

    public function test_manager_can_update_and_delete_a_holiday(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $holiday = Holiday::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'name' => 'Old Name',
            'date' => '2026-08-10',
        ]);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('editHoliday', $holiday->id)
            ->assertSet('holidayName', 'Old Name')
            ->set('holidayName', 'New Name')
            ->call('saveHoliday')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $holiday->fresh()->name);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('deleteHoliday', $holiday->id);

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_duplicate_holiday_date_for_the_same_team_is_rejected(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        Holiday::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'date' => '2026-08-10',
        ]);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->set('holidayName', 'Duplicate')
            ->set('holidayDate', '2026-08-10')
            ->call('saveHoliday')
            ->assertHasErrors('holidayDate');
    }

    public function test_manager_cannot_manage_another_teams_holidays(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $otherTeam = $this->makeOwner()->currentTeam;

        // Tampering with teamId is rejected as soon as the component re-renders,
        // so the manager never reaches another team's holidays at all.
        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->set('teamId', $otherTeam->id)
            ->assertForbidden();

        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_admin_can_manage_any_teams_holidays(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $team = $this->makeOwner()->currentTeam;

        Livewire::actingAs($admin)
            ->test(LeaveSettings::class)
            ->set('teamId', $team->id)
            ->set('holidayName', 'Company Retreat')
            ->set('holidayDate', '2026-09-01')
            ->call('saveHoliday')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('holidays', [
            'team_id' => $team->id,
            'name' => 'Company Retreat',
        ]);
    }

    // ── Per-member days off ───────────────────────────────────────────────────

    public function test_manager_can_edit_a_team_members_days_off(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('editMember', $employee->id)
            ->assertSet('memberTotalDays', 20)
            ->set('memberTotalDays', 26)
            ->call('saveMember')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'year' => now()->year,
            'total_days' => 26,
        ]);
    }

    public function test_editing_days_off_keeps_used_days_and_updates_remaining(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'year' => now()->year,
            'total_days' => 20,
            'used_days' => 8,
        ]);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('editMember', $employee->id)
            ->set('memberTotalDays', 30)
            ->call('saveMember');

        $balance = LeaveBalance::where('user_id', $employee->id)->first();

        $this->assertSame(30, $balance->total_days);
        $this->assertSame(8, $balance->used_days);
        $this->assertSame(22, $balance->remainingDays());
    }

    public function test_days_off_must_be_a_sane_number(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('editMember', $employee->id)
            ->set('memberTotalDays', -5)
            ->call('saveMember')
            ->assertHasErrors('memberTotalDays');
    }

    public function test_manager_cannot_edit_days_off_for_another_teams_member(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $otherOwner = $this->makeOwner();
        $otherEmployee = $this->makeEmployee($otherOwner->currentTeam);

        Livewire::actingAs($manager)
            ->test(LeaveSettings::class)
            ->call('editMember', $otherEmployee->id)
            ->assertForbidden();
    }

    public function test_admin_can_edit_days_off_for_any_teams_member(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = $this->makeEmployee($this->makeOwner()->currentTeam);

        Livewire::actingAs($admin)
            ->test(LeaveSettings::class)
            ->call('editMember', $employee->id)
            ->set('memberTotalDays', 15)
            ->call('saveMember')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'total_days' => 15,
        ]);
    }
}