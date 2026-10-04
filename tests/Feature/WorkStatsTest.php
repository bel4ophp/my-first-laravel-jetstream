<?php

namespace Tests\Feature;

use App\Livewire\WorkStats;
use App\Models\LeaveBalance;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class WorkStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pinned mid-month: several tests put an entry on "yesterday" and expect it
     * in this month's total, which fails on the 1st when yesterday is last month.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    // ── Admin ─────────────────────────────────────────────────────────────────

    private function createAdminWithWorkTeam(): array
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $team = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $admin->switchTeam($team);

        return [$admin, $team];
    }

    public function test_admin_current_team_shows_team_monthly_hours(): void
    {
        [$admin, $team] = $this->createAdminWithWorkTeam();

        $employee = User::factory()->create();
        $team->users()->attach($employee, ['role' => 'employee']);

        // admin + employee each work 1 day = 16h total
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $admin->id]);
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $employee->id]);

        $this->actingAs($admin);

        Livewire::test(WorkStats::class)
            ->assertSet('isOwner', true)
            ->assertSet('label', $team->name)
            ->assertSet('monthlyHours', '16h');
    }

    public function test_admin_all_teams_aggregates_across_owned_work_teams(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();

        $teamOne = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $teamTwo = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $admin->switchTeam($teamOne);

        $employeeA = User::factory()->create();
        $teamOne->users()->attach($employeeA, ['role' => 'employee']);

        $employeeB = User::factory()->create();
        $teamTwo->users()->attach($employeeB, ['role' => 'employee']);

        // employeeA: 8h, employeeB: 8h, admin counted in both teams but deduplicated → 8h
        // total unique members: admin + A + B = 3 people × 8h = 24h
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $employeeA->id]);
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $employeeB->id]);
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        Livewire::test(WorkStats::class, ['allTeams' => true])
            ->assertSet('isOwner', true)
            ->assertSet('label', 'All Teams')
            ->assertSet('monthlyHours', '24h');
    }

    public function test_admin_all_teams_active_now_spans_all_work_teams(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();

        $teamOne = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $teamTwo = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $admin->switchTeam($teamOne);

        $employeeA = User::factory()->create();
        $teamOne->users()->attach($employeeA, ['role' => 'employee']);

        $employeeB = User::factory()->create();
        $teamTwo->users()->attach($employeeB, ['role' => 'employee']);

        // employeeA active, employeeB not — admin + A (team1) + B (team2) = 3 unique, 1 active
        TimeEntry::factory()->forDay(today()->format('Y-m-d'))->active()->create(['user_id' => $employeeA->id]);

        $this->actingAs($admin);

        Livewire::test(WorkStats::class, ['allTeams' => true])
            ->assertSet('activeNow', '1 / 3');
    }

    public function test_admin_personal_team_excluded_from_all_teams_aggregate(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $workTeam = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $admin->switchTeam($workTeam);

        // Entry only for the personal team context — should not appear in all-teams stats
        // (personal team has no extra members, admin is deduplicated, so 0h from personal team)
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        // Admin is member of workTeam → counted once = 8h
        Livewire::test(WorkStats::class, ['allTeams' => true])
            ->assertSet('monthlyHours', '8h');
    }

    // ── Admin exclusion ───────────────────────────────────────────────────────

    public function test_admin_hours_excluded_from_team_monthly_hours(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_admin' => true]);
        $team = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $admin->switchTeam($team);

        $employee = User::factory()->create();
        $team->users()->attach($employee, ['role' => 'employee']);

        // admin works 8h, employee works 8h — only employee's 8h should count
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $admin->id]);
        TimeEntry::factory()->forDay(now()->startOfMonth()->format('Y-m-d'))->create(['user_id' => $employee->id]);

        $this->actingAs($admin);

        Livewire::test(WorkStats::class)
            ->assertSet('monthlyHours', '8h')
            ->assertSet('activeNow', '0 / 1');
    }

    public function test_admin_excluded_from_active_now_count_and_denominator(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_admin' => true]);
        $team = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $admin->switchTeam($team);

        $employee = User::factory()->create();
        $team->users()->attach($employee, ['role' => 'employee']);

        // Only admin is clocked in — should not appear in active count or total
        TimeEntry::factory()->forDay(today()->format('Y-m-d'))->active()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        Livewire::test(WorkStats::class)
            ->assertSet('activeNow', '0 / 1');
    }

    /**
     * The figures used to be fixed at mount(), so the open dashboard never
     * showed anyone clocking in after it loaded.
     */
    public function test_the_tile_picks_up_a_clock_in_on_its_next_refresh(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $manager = User::factory()->create();
        $owner->currentTeam->users()->attach($manager, ['role' => 'manager']);
        $manager->switchTeam($owner->currentTeam);
        $employee = User::factory()->create();
        $owner->currentTeam->users()->attach($employee, ['role' => 'employee']);

        $this->actingAs($manager);
        $tile = Livewire::test(WorkStats::class)->assertSet('activeNow', '0 / 3');

        TimeEntry::factory()->forDay(today()->format('Y-m-d'))->active()->create(['user_id' => $employee->id]);

        $tile->call('$refresh')->assertSet('activeNow', '1 / 3');
    }

    public function test_someone_with_two_open_entries_counts_as_one_person_in(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $manager = User::factory()->create();
        $owner->currentTeam->users()->attach($manager, ['role' => 'manager']);
        $manager->switchTeam($owner->currentTeam);
        $employee = User::factory()->create();
        $owner->currentTeam->users()->attach($employee, ['role' => 'employee']);

        TimeEntry::factory()->count(2)->forDay(today()->format('Y-m-d'))->active()->create(['user_id' => $employee->id]);

        $this->actingAs($manager);

        Livewire::test(WorkStats::class)->assertSet('activeNow', '1 / 3');
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

    // ── Free days ─────────────────────────────────────────────────────────────

    public function test_free_days_reflects_the_users_actual_leave_balance(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'year' => now()->year,
            'total_days' => 25,
            'used_days' => 7,
        ]);

        $this->actingAs($user);

        Livewire::test(WorkStats::class)->assertSet('freeDays', '25/7');
    }

    public function test_free_days_falls_back_to_a_freshly_created_balance(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);

        Livewire::test(WorkStats::class)
            ->assertSet('freeDays', LeaveBalanceService::DEFAULT_POOL_DAYS.'/0');

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'year' => now()->year,
            'used_days' => 0,
        ]);
    }
}
