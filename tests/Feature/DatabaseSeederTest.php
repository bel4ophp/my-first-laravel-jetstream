<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_is_created_with_correct_attributes(): void
    {
        $admin = User::where('email', 'admin@bel4o.dev')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_admin);
        $this->assertNotNull($admin->current_team_id);
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_admin_is_switched_to_first_work_team(): void
    {
        $admin = User::where('email', 'admin@bel4o.dev')->first();
        $firstWorkTeam = $admin->ownedTeams()->where('personal_team', false)->orderBy('id')->first();

        $this->assertEquals($firstWorkTeam->id, $admin->current_team_id);
    }

    public function test_five_work_teams_are_created_for_admin(): void
    {
        $admin = User::where('email', 'admin@bel4o.dev')->first();

        $this->assertCount(5, $admin->ownedTeams()->where('personal_team', false)->get());
    }

    public function test_each_team_has_one_manager_and_three_employees(): void
    {
        $admin = User::where('email', 'admin@bel4o.dev')->first();
        $workTeams = $admin->ownedTeams()->where('personal_team', false)->get();

        foreach ($workTeams as $team) {
            $this->assertEquals(1, $team->users()->wherePivot('role', 'manager')->count(), "{$team->name} needs 1 manager");
            $this->assertEquals(3, $team->users()->wherePivot('role', 'employee')->count(), "{$team->name} needs 3 employees");
        }
    }

    public function test_manager_and_employees_are_switched_to_their_team(): void
    {
        $admin = User::where('email', 'admin@bel4o.dev')->first();
        $team = $admin->ownedTeams()->where('personal_team', false)->first();

        $members = $team->users()->wherePivotIn('role', ['manager', 'employee'])->get();

        foreach ($members as $member) {
            $this->assertEquals($team->id, $member->current_team_id, "{$member->name} should be switched to {$team->name}");
        }
    }

    public function test_employees_have_time_entries_for_past_weekdays_this_month(): void
    {
        $pastWeekdays = $this->countPastWeekdaysThisMonth();

        if ($pastWeekdays === 0) {
            $this->markTestSkipped('No past weekdays this month — time entry seeding cannot be verified today.');
        }

        $admin = User::where('email', 'admin@bel4o.dev')->first();
        $team = $admin->ownedTeams()->where('personal_team', false)->first();
        $employee = $team->users()->wherePivot('role', 'employee')->first();

        $entryCount = TimeEntry::where('user_id', $employee->id)
            ->whereMonth('work_day', now()->month)
            ->whereYear('work_day', now()->year)
            ->count();

        $this->assertEquals($pastWeekdays, $entryCount);
    }

    public function test_time_entries_are_complete_8h_days(): void
    {
        $admin = User::where('email', 'admin@bel4o.dev')->first();
        $team = $admin->ownedTeams()->where('personal_team', false)->first();
        $employee = $team->users()->wherePivot('role', 'employee')->first();

        TimeEntry::where('user_id', $employee->id)->each(function (TimeEntry $entry) {
            $this->assertEquals(480, $entry->worked_minutes);
            $this->assertNotNull($entry->clock_in);
            $this->assertNotNull($entry->clock_out);
        });
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEquals(1, User::where('email', 'admin@bel4o.dev')->count());

        $admin = User::where('email', 'admin@bel4o.dev')->first();
        $this->assertCount(5, $admin->ownedTeams()->where('personal_team', false)->get());
    }

    private function countPastWeekdaysThisMonth(): int
    {
        $count = 0;
        $start = now()->startOfMonth()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        for ($day = $start->copy(); $day->lte($yesterday); $day->addDay()) {
            if ($day->isWeekday()) {
                $count++;
            }
        }

        return $count;
    }
}