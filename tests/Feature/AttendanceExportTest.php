<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class AttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // JetstreamServiceProvider skips role registration when runningInConsole(),
        // so we mirror RolePermissionSeeder's permission assignments here.
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

    private function makeMember(Team $team): User
    {
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => 'employee']);

        return $member;
    }

    private function exportUrl(array $params = []): string
    {
        return route('reports.attendance.export', $params);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_export(): void
    {
        $this->get($this->exportUrl(['type' => 'monthly', 'year' => 2026, 'month' => 1]))
            ->assertRedirect(route('login'));
    }

    public function test_team_member_cannot_export(): void
    {
        $owner  = $this->makeOwner();
        $member = $this->makeMember($owner->currentTeam);

        $this->actingAs($member)
            ->get($this->exportUrl(['type' => 'monthly', 'year' => 2026, 'month' => 1]))
            ->assertForbidden();
    }

    public function test_team_owner_can_export(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'monthly', 'year' => now()->year, 'month' => now()->month]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_manager_can_export(): void
    {
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $this->actingAs($manager)
            ->get($this->exportUrl(['type' => 'monthly', 'year' => now()->year, 'month' => now()->month]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    // ── Daily export ──────────────────────────────────────────────────────────

    public function test_daily_export_returns_csv_for_the_given_date(): void
    {
        $owner = $this->makeOwner();
        $date  = '2026-05-15';
        TimeEntry::factory()->forDay($date)->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay('2026-05-16')->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'daily', 'date' => $date]));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString($date, $response->streamedContent());
        $this->assertStringNotContainsString('2026-05-16', $response->streamedContent());
    }

    // ── Weekly export ─────────────────────────────────────────────────────────

    public function test_weekly_export_covers_monday_to_sunday(): void
    {
        $owner     = $this->makeOwner();
        $wednesday = '2026-05-13'; // a Wednesday
        $monday    = '2026-05-11';
        $sunday    = '2026-05-17';
        $nextWeek  = '2026-05-18';

        TimeEntry::factory()->forDay($monday)->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay($sunday)->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay($nextWeek)->create(['user_id' => $owner->id]);

        $content = $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'weekly', 'week_date' => $wednesday]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($monday, $content);
        $this->assertStringContainsString($sunday, $content);
        $this->assertStringNotContainsString($nextWeek, $content);
    }

    // ── Monthly export ────────────────────────────────────────────────────────

    public function test_monthly_export_covers_the_full_month(): void
    {
        $owner = $this->makeOwner();
        TimeEntry::factory()->forDay('2026-03-01')->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay('2026-03-31')->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay('2026-04-01')->create(['user_id' => $owner->id]);

        $content = $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'monthly', 'year' => 2026, 'month' => 3]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('2026-03-01', $content);
        $this->assertStringContainsString('2026-03-31', $content);
        $this->assertStringNotContainsString('2026-04-01', $content);
    }

    // ── Yearly export ─────────────────────────────────────────────────────────

    public function test_yearly_export_covers_the_full_year(): void
    {
        $owner = $this->makeOwner();
        TimeEntry::factory()->forDay('2025-01-01')->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay('2025-12-31')->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay('2026-01-01')->create(['user_id' => $owner->id]);

        $content = $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'yearly', 'year' => 2025]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('2025-01-01', $content);
        $this->assertStringContainsString('2025-12-31', $content);
        $this->assertStringNotContainsString('2026-01-01', $content);
    }

    // ── Custom range export ───────────────────────────────────────────────────

    public function test_custom_range_export_covers_start_to_end_inclusive(): void
    {
        $owner = $this->makeOwner();
        TimeEntry::factory()->forDay('2026-02-10')->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay('2026-02-20')->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay('2026-02-21')->create(['user_id' => $owner->id]);

        $content = $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'custom', 'start_date' => '2026-02-10', 'end_date' => '2026-02-20']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('2026-02-10', $content);
        $this->assertStringContainsString('2026-02-20', $content);
        $this->assertStringNotContainsString('2026-02-21', $content);
    }

    public function test_custom_range_requires_both_dates(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'custom', 'start_date' => '2026-02-01']))
            ->assertSessionHasErrors('end_date');
    }

    public function test_custom_range_end_must_not_be_before_start(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'custom', 'start_date' => '2026-02-20', 'end_date' => '2026-02-10']))
            ->assertSessionHasErrors('end_date');
    }

    // ── CSV structure ─────────────────────────────────────────────────────────

    public function test_csv_contains_header_row_and_entry_data(): void
    {
        $owner = $this->makeOwner();
        TimeEntry::factory()->forDay('2026-04-01')->create(['user_id' => $owner->id]);

        $content = $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'monthly', 'year' => 2026, 'month' => 4]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Employee', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('Clock In', $content);
        $this->assertStringContainsString($owner->name, $content);
        $this->assertStringContainsString($owner->email, $content);
    }

    public function test_export_does_not_include_other_teams_entries(): void
    {
        $owner       = $this->makeOwner();
        $otherOwner  = User::factory()->withPersonalTeam()->create();
        $date        = '2026-04-01';

        TimeEntry::factory()->forDay($date)->create(['user_id' => $owner->id]);
        TimeEntry::factory()->forDay($date)->create(['user_id' => $otherOwner->id]);

        $content = $this->actingAs($owner)
            ->get($this->exportUrl(['type' => 'monthly', 'year' => 2026, 'month' => 4]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($owner->email, $content);
        $this->assertStringNotContainsString($otherOwner->email, $content);
    }
}