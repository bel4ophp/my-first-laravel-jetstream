<?php

namespace Tests\Feature;

use App\Livewire\AttendanceCalendar;
use App\Livewire\AttendanceDayPanel;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceCalendarCanViewTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function makeEntry(User $user, string $workDay): TimeEntry
    {
        return TimeEntry::factory()->forDay($workDay)->create(['user_id' => $user->id]);
    }

    // ── canViewTimeEntries ────────────────────────────────────────────────────

    public function test_owner_can_view_time_entries(): void
    {
        $owner = $this->makeOwner();

        Livewire::actingAs($owner)
            ->test(AttendanceCalendar::class)
            ->assertSet('canViewTimeEntries', true);
    }

    public function test_manager_can_view_time_entries(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        Livewire::actingAs($manager)
            ->test(AttendanceCalendar::class)
            ->assertSet('canViewTimeEntries', true);
    }

    public function test_employee_can_view_time_entries(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($employee)
            ->test(AttendanceCalendar::class)
            ->assertSet('canViewTimeEntries', true);
    }

    // ── Data scoping ──────────────────────────────────────────────────────────

    /**
     * Employees see their whole team's clock-ins, so they can tell who is in
     * today to talk to and work with.
     */
    public function test_employee_sees_their_teams_entries(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);
        $colleague = $this->makeEmployee($owner->currentTeam);

        $workDay = now()->subDay()->toDateString();
        $year = now()->subDay()->year;
        $month = now()->subDay()->month;

        $ownEntry = $this->makeEntry($employee, $workDay);
        $colleagueEntry = $this->makeEntry($colleague, $workDay);

        $allIds = Livewire::actingAs($employee)
            ->test(AttendanceCalendar::class, ['year' => $year, 'month' => $month])
            ->get('entriesByDay')->flatten()->pluck('id');

        $this->assertTrue($allIds->contains($ownEntry->id));
        $this->assertTrue($allIds->contains($colleagueEntry->id));
    }

    public function test_employee_does_not_see_other_teams_entries(): void
    {
        $employee = $this->makeEmployee($this->makeOwner()->currentTeam);
        $stranger = $this->makeEmployee($this->makeOwner()->currentTeam);

        $workDay = now()->subDay()->toDateString();
        $strangerEntry = $this->makeEntry($stranger, $workDay);

        $allIds = Livewire::actingAs($employee)
            ->test(AttendanceCalendar::class, ['year' => now()->subDay()->year, 'month' => now()->subDay()->month])
            ->get('entriesByDay')->flatten()->pluck('id');

        $this->assertFalse($allIds->contains($strangerEntry->id));
    }

    /**
     * Seeing the team is read-only: editing, deleting, adding and exporting
     * stay with managers and the admin.
     */
    public function test_employee_sees_the_team_read_only(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($employee)
            ->test(AttendanceCalendar::class)
            ->assertSet('canExportAttendance', false);

        Livewire::actingAs($employee)
            ->test(AttendanceDayPanel::class, ['date' => now()->subDay()->toDateString()])
            ->assertSet('canCreateTimeEntries', false)
            ->assertSet('canUpdateTimeEntries', false)
            ->assertSet('canDeleteTimeEntries', false);

        $this->actingAs($employee)
            ->get(route('reports.attendance.export', ['type' => 'monthly']))
            ->assertForbidden();
    }

    public function test_manager_sees_all_team_entries(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        $workDay = now()->subDay()->toDateString();
        $year = now()->subDay()->year;
        $month = now()->subDay()->month;

        $managerEntry = $this->makeEntry($manager, $workDay);
        $employeeEntry = $this->makeEntry($employee, $workDay);

        $component = Livewire::actingAs($manager)
            ->test(AttendanceCalendar::class, ['year' => $year, 'month' => $month]);

        $allIds = $component->get('entriesByDay')->flatten()->pluck('id');

        $this->assertTrue($allIds->contains($managerEntry->id));
        $this->assertTrue($allIds->contains($employeeEntry->id));
    }

    public function test_entries_by_day_is_empty_when_user_has_no_team(): void
    {
        $loneUser = User::factory()->create(['current_team_id' => null]);

        $component = Livewire::actingAs($loneUser)
            ->test(AttendanceCalendar::class);

        $this->assertTrue($component->get('entriesByDay')->isEmpty());
    }

    // ── Scoping cost ──────────────────────────────────────────────────────────

    /**
     * An owner's visible set is built by walking their owned teams, so it used
     * to cost two queries per team. Asserting the count is *constant* rather
     * than pinning a number keeps this honest if the query shape changes.
     */
    public function test_an_owners_scope_costs_the_same_regardless_of_how_many_teams_they_own(): void
    {
        $service = app(AttendanceCalendarService::class);

        $withOneTeam = $this->makeOwner();
        $this->makeEmployee($withOneTeam->currentTeam);

        $withFourTeams = $this->makeOwner();
        for ($i = 0; $i < 3; $i++) {
            $extra = Team::forceCreate([
                'user_id' => $withFourTeams->id,
                'name' => "Extra Team {$i}",
                'personal_team' => false,
            ]);
            $this->makeEmployee($extra);
        }
        $this->makeEmployee($withFourTeams->currentTeam);

        $countQueries = function (User $owner) use ($service): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->scopedUserIds($owner->fresh(), null);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->assertSame(4, $withFourTeams->fresh()->ownedTeams()->count());
        $this->assertSame($countQueries($withOneTeam), $countQueries($withFourTeams));
    }
}
