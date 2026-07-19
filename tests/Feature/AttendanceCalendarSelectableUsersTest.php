<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AttendanceCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCalendarSelectableUsersTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceCalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AttendanceCalendarService;
    }

    public function test_admin_users_are_excluded_for_a_global_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $regular = User::factory()->create();

        $ids = $this->service->selectableUsers($admin)->pluck('id');

        $this->assertTrue($ids->contains($regular->id));
        $this->assertFalse($ids->contains($admin->id));
        $this->assertFalse($ids->contains($otherAdmin->id));
    }

    public function test_admin_users_are_excluded_for_a_team_owner(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->withPersonalTeam()->create();

        $ids = $this->service->selectableUsers($owner)->pluck('id');

        $this->assertTrue($ids->contains($owner->id));
        $this->assertFalse($ids->contains($admin->id));
    }

    public function test_admin_team_members_are_excluded_for_a_non_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager, ['role' => 'manager']);

        $adminMember = User::factory()->create(['is_admin' => true, 'current_team_id' => $team->id]);
        $team->users()->attach($adminMember, ['role' => 'employee']);

        $ids = $this->service->selectableUsers($manager)->pluck('id');

        $this->assertTrue($ids->contains($manager->id));
        $this->assertFalse($ids->contains($adminMember->id));
    }
}