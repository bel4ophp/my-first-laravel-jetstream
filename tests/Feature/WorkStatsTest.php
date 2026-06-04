<?php

namespace Tests\Feature;

use App\Livewire\WorkStats;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkStatsTest extends TestCase
{
    use RefreshDatabase;

    // ── Employee ──────────────────────────────────────────────────────────────

    public function test_employee_sees_own_monthly_hours_from_completed_entries(): void
    {
        $employee = User::factory()->withPersonalTeam()->create();
        $this->actingAs($employee);

        // 2 completed days × 480 min = 960 min = 16h
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $employee->id]);
        TimeEntry::factory()->forDay(now()->startOfMonth()->addDay()->format('Y-m-d'))->create(['user_id' => $employee->id]);

        Livewire::test(WorkStats::class)
            ->assertSet('isManager', false)
            ->assertSet('monthlyHours', '16h')
            ->assertSet('activeNow', null);
    }

    public function test_employee_active_session_today_adds_8h(): void
    {
        $employee = User::factory()->withPersonalTeam()->create();
        $this->actingAs($employee);

        // 1 completed day (8h) + 1 active session today → 8 + 8 = 16h
        TimeEntry::factory()->forDay(now()->subDay()->format('Y-m-d'))->create(['user_id' => $employee->id]);
        TimeEntry::factory()->forDay(today()->format('Y-m-d'))->active()->create(['user_id' => $employee->id]);

        Livewire::test(WorkStats::class)
            ->assertSet('monthlyHours', '16h');
    }

    public function test_employee_with_no_entries_shows_zero_hours(): void
    {
        $employee = User::factory()->withPersonalTeam()->create();
        $this->actingAs($employee);

        Livewire::test(WorkStats::class)
            ->assertSet('monthlyHours', '0h');
    }

    public function test_employee_entries_from_previous_month_are_excluded(): void
    {
        $employee = User::factory()->withPersonalTeam()->create();
        $this->actingAs($employee);

        $lastMonth = now()->subMonth()->format('Y-m-d');
        TimeEntry::factory()->forDay($lastMonth)->create(['user_id' => $employee->id]);

        Livewire::test(WorkStats::class)
            ->assertSet('monthlyHours', '0h');
    }

    // ── Manager ───────────────────────────────────────────────────────────────

    public function test_manager_sees_team_monthly_hours(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $manager = User::factory()->create();
        $owner->currentTeam->users()->attach($manager, ['role' => 'manager']);
        $manager->switchTeam($owner->currentTeam);

        $employee = User::factory()->create();
        $owner->currentTeam->users()->attach($employee, ['role' => 'employee']);

        // employee: 2 completed days = 16h; manager: 1 completed day = 8h → team = 24h
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $employee->id]);
        TimeEntry::factory()->forDay(now()->startOfMonth()->addDay()->format('Y-m-d'))->create(['user_id' => $employee->id]);
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $manager->id]);

        $this->actingAs($manager);

        Livewire::test(WorkStats::class)
            ->assertSet('isManager', true)
            ->assertSet('monthlyHours', '24h');
    }

    public function test_manager_active_team_members_each_add_8h(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $manager = User::factory()->create();
        $owner->currentTeam->users()->attach($manager, ['role' => 'manager']);
        $manager->switchTeam($owner->currentTeam);

        $employee = User::factory()->create();
        $owner->currentTeam->users()->attach($employee, ['role' => 'employee']);

        // employee has active session today → adds 480 min (8h)
        TimeEntry::factory()->forDay(today()->format('Y-m-d'))->active()->create(['user_id' => $employee->id]);

        $this->actingAs($manager);

        Livewire::test(WorkStats::class)
            ->assertSet('monthlyHours', '8h');
    }

    public function test_manager_sees_active_now_count(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $manager = User::factory()->create();
        $owner->currentTeam->users()->attach($manager, ['role' => 'manager']);
        $manager->switchTeam($owner->currentTeam);

        $employeeA = User::factory()->create();
        $owner->currentTeam->users()->attach($employeeA, ['role' => 'employee']);

        $employeeB = User::factory()->create();
        $owner->currentTeam->users()->attach($employeeB, ['role' => 'employee']);

        // employeeA is active, employeeB is not — team has 4 members (owner + manager + A + B)
        TimeEntry::factory()->forDay(today()->format('Y-m-d'))->active()->create(['user_id' => $employeeA->id]);

        $this->actingAs($manager);

        Livewire::test(WorkStats::class)
            ->assertSet('activeNow', '1 / 4');
    }
}