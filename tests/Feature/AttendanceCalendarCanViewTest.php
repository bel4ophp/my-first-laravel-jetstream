<?php

namespace Tests\Feature;

use App\Livewire\AttendanceCalendar;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceCalendarCanViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Jetstream::role('admin',    'Administrator', ['*']);
        Jetstream::role('manager',  'Manager',       ['read', 'update', 'view-attendance', 'create-time-entries', 'update-time-entries', 'add-team-member', 'update-team-member', 'remove-team-member']);
        Jetstream::role('employee', 'Employee',      ['read']);
    }

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
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        Livewire::actingAs($manager)
            ->test(AttendanceCalendar::class)
            ->assertSet('canViewTimeEntries', true);
    }

    public function test_employee_can_view_time_entries(): void
    {
        $owner    = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($employee)
            ->test(AttendanceCalendar::class)
            ->assertSet('canViewTimeEntries', true);
    }

    // ── Data scoping ──────────────────────────────────────────────────────────

    public function test_employee_sees_only_own_entries(): void
    {
        $owner    = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);
        $other    = $this->makeEmployee($owner->currentTeam);

        $workDay = now()->subDay()->toDateString();
        $year    = now()->subDay()->year;
        $month   = now()->subDay()->month;

        $ownEntry   = $this->makeEntry($employee, $workDay);
        $otherEntry = $this->makeEntry($other, $workDay);

        $component = Livewire::actingAs($employee)
            ->test(AttendanceCalendar::class, ['year' => $year, 'month' => $month]);

        $allIds = $component->get('entriesByDay')->flatten()->pluck('id');

        $this->assertTrue($allIds->contains($ownEntry->id));
        $this->assertFalse($allIds->contains($otherEntry->id));
    }

    public function test_manager_sees_all_team_entries(): void
    {
        $owner    = $this->makeOwner();
        $manager  = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        $workDay = now()->subDay()->toDateString();
        $year    = now()->subDay()->year;
        $month   = now()->subDay()->month;

        $managerEntry  = $this->makeEntry($manager, $workDay);
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
}