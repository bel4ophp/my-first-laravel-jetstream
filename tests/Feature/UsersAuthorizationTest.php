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

    // ── Index listing scope ───────────────────────────────────────────────────

    /**
     * The list was scoped only when isTeamManager() was true, which is never
     * the case for an owner — they hold no team_user row. So the query ran
     * unscoped and the page listed every user in the system, including other
     * owners' teams. They could not edit those users (UserPolicy blocks it),
     * but their names and emails were on screen.
     */
    public function test_an_owners_list_excludes_users_from_another_owners_team(): void
    {
        $owner = $this->makeOwner();
        $mine = $this->makeEmployee($owner->currentTeam);

        $otherOwner = $this->makeOwner();
        $theirs = $this->makeEmployee($otherOwner->currentTeam);

        $response = $this->actingAs($owner)->get(route('users.index'))->assertOk();

        $listed = $response->viewData('users')->pluck('id');

        $this->assertTrue($listed->contains($mine->id));
        $this->assertFalse($listed->contains($theirs->id));
    }

    public function test_an_admin_still_sees_every_user(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);

        $otherOwner = $this->makeOwner();
        $theirs = $this->makeEmployee($otherOwner->currentTeam);

        $listed = $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->viewData('users')->pluck('id');

        $this->assertTrue($listed->contains($theirs->id));
    }

    public function test_search_still_narrows_within_the_owners_scope(): void
    {
        $owner = $this->makeOwner();
        $match = $this->makeEmployee($owner->currentTeam);
        $match->forceFill(['name' => 'Findable Person'])->save();
        $otherMember = $this->makeEmployee($owner->currentTeam);

        $listed = $this->actingAs($owner)->get(route('users.index', ['search' => 'Findable']))
            ->assertOk()
            ->viewData('users')
            ->pluck('id');

        $this->assertTrue($listed->contains($match->id));
        $this->assertFalse($listed->contains($otherMember->id));
    }

    // ── Cross-team scoping ────────────────────────────────────────────────────

    public function test_owner_can_delete_a_user_on_their_own_team(): void
    {
        $owner  = $this->makeOwner();
        $target = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($owner)->delete(route('users.destroy', $target))->assertRedirect(route('users.index'));

        $this->assertModelMissing($target);
    }

    public function test_owner_cannot_delete_a_user_on_another_owners_team(): void
    {
        $owner      = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $target     = $this->makeEmployee($otherOwner->currentTeam);

        $this->actingAs($owner)->delete(route('users.destroy', $target))->assertForbidden();

        $this->assertModelExists($target);
    }

    public function test_owner_cannot_update_a_user_on_another_owners_team(): void
    {
        $owner      = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $target     = $this->makeEmployee($otherOwner->currentTeam);

        $this->actingAs($owner)
            ->put(route('users.update', $target), ['name' => 'Renamed', 'email' => 'renamed@example.com'])
            ->assertForbidden();

        $this->assertSame($target->name, $target->fresh()->name);
    }

    public function test_owner_cannot_edit_a_user_on_another_owners_team(): void
    {
        $owner      = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $target     = $this->makeEmployee($otherOwner->currentTeam);

        $this->actingAs($owner)->get(route('users.edit', $target))->assertForbidden();
    }

    // ── Delete guards ─────────────────────────────────────────────────────────

    public function test_owner_cannot_delete_themselves(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->delete(route('users.destroy', $owner))->assertForbidden();

        $this->assertModelExists($owner);
    }

    public function test_owner_cannot_delete_a_global_admin(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);
        $owner->currentTeam->users()->attach($admin, ['role' => 'employee']);

        $this->actingAs($owner)->delete(route('users.destroy', $admin))->assertForbidden();

        $this->assertModelExists($admin);
    }
}