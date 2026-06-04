<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class UsersAuthorizationTest extends TestCase
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

    // ── users.index ───────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_users_index(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_team_owner_can_access_users_index(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('users.index'))->assertOk();
    }

    public function test_is_admin_user_can_access_users_index(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }

    public function test_manager_cannot_access_users_index(): void
    {
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
    }

    public function test_employee_cannot_access_users_index(): void
    {
        $owner    = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($employee)->get(route('users.index'))->assertForbidden();
    }

    // ── users.create / store ──────────────────────────────────────────────────

    public function test_manager_cannot_access_users_create(): void
    {
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $this->actingAs($manager)->get(route('users.create'))->assertForbidden();
    }

    public function test_manager_cannot_store_user(): void
    {
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $this->actingAs($manager)->post(route('users.store'), [])->assertForbidden();
    }

    // ── users.edit / update / destroy ─────────────────────────────────────────

    public function test_manager_cannot_edit_user(): void
    {
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $target  = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($manager)->get(route('users.edit', $target))->assertForbidden();
    }

    public function test_manager_cannot_update_user(): void
    {
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $target  = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($manager)->put(route('users.update', $target), [])->assertForbidden();
    }

    public function test_manager_cannot_delete_user(): void
    {
        $owner   = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $target  = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($manager)->delete(route('users.destroy', $target))->assertForbidden();
    }
}