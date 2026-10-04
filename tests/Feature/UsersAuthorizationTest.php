<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class UsersAuthorizationTest extends TestCase
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

    // ── users.index ───────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_users_index(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    /**
     * The Users screen is admin-only. Owning a team grants nothing here; in this
     * app every work team is owned by the admin anyway.
     */
    public function test_a_non_admin_team_owner_cannot_access_users_index(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('users.index'))->assertForbidden();
    }

    public function test_is_admin_user_can_access_users_index(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }

    public function test_manager_cannot_access_users_index(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
    }

    public function test_employee_cannot_access_users_index(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($employee)->get(route('users.index'))->assertForbidden();
    }

    // ── users.create / store ──────────────────────────────────────────────────

    public function test_manager_cannot_access_users_create(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $this->actingAs($manager)->get(route('users.create'))->assertForbidden();
    }

    public function test_manager_cannot_store_user(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $this->actingAs($manager)->post(route('users.store'), [])->assertForbidden();
    }

    // ── users.edit / update / destroy ─────────────────────────────────────────

    public function test_manager_cannot_edit_user(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $target = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($manager)->get(route('users.edit', $target))->assertForbidden();
    }

    public function test_manager_cannot_update_user(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $target = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($manager)->put(route('users.update', $target), [])->assertForbidden();
    }

    public function test_manager_cannot_delete_user(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $target = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($manager)->delete(route('users.destroy', $target))->assertForbidden();
    }

    // ── Index listing scope ───────────────────────────────────────────────────

    /**
     * Owners used to get a list scoped to their own teams. The screen is now
     * admin-only, so an owner gets no list at all — not even their own team.
     */
    public function test_a_non_admin_owner_cannot_list_even_their_own_teams_users(): void
    {
        $owner = $this->makeOwner();
        $this->makeEmployee($owner->currentTeam);

        $this->actingAs($owner)->get(route('users.index'))->assertForbidden();
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

    /**
     * % and _ are LIKE wildcards; typed into the search box they used to match
     * every user instead of only names containing those characters.
     */
    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);
        $literal = $this->makeEmployee($owner->currentTeam);
        $literal->forceFill(['name' => 'Ana_Petrović'])->save();
        $other = $this->makeEmployee($owner->currentTeam);
        $other->forceFill(['name' => 'AnaXPetrović'])->save();

        $percent = $this->actingAs($admin)->get(route('users.index', ['search' => '%']))
            ->assertOk()->viewData('users')->pluck('id');
        $underscore = $this->actingAs($admin)->get(route('users.index', ['search' => 'Ana_']))
            ->assertOk()->viewData('users')->pluck('id');

        $this->assertCount(0, $percent);
        $this->assertTrue($underscore->contains($literal->id));
        $this->assertFalse($underscore->contains($other->id));
    }

    public function test_search_narrows_the_admins_list(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);
        $match = $this->makeEmployee($owner->currentTeam);
        $match->forceFill(['name' => 'Findable Person'])->save();
        $otherMember = $this->makeEmployee($owner->currentTeam);

        $listed = $this->actingAs($admin)->get(route('users.index', ['search' => 'Findable']))
            ->assertOk()
            ->viewData('users')
            ->pluck('id');

        $this->assertTrue($listed->contains($match->id));
        $this->assertFalse($listed->contains($otherMember->id));
    }

    // ── Cross-team scoping ────────────────────────────────────────────────────

    public function test_a_non_admin_owner_cannot_delete_a_user_even_on_their_own_team(): void
    {
        $owner = $this->makeOwner();
        $target = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($owner)->delete(route('users.destroy', $target))->assertForbidden();

        $this->assertModelExists($target);
    }

    // ── Deleting users (admin) ────────────────────────────────────────────────

    public function test_the_admin_can_delete_an_employee(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);
        $target = $this->makeEmployee($owner->currentTeam);

        $this->actingAs($admin)->delete(route('users.destroy', $target))->assertRedirect(route('users.index'));

        $this->assertModelMissing($target);
    }

    /**
     * team_user has no foreign keys and tokens/notifications are polymorphic, so
     * a bare $user->delete() left all three behind. Deleting goes through the
     * same DeleteUser action as Jetstream's own account deletion.
     */
    public function test_deleting_a_user_removes_their_memberships_tokens_and_notifications(): void
    {
        $owner = $this->makeOwner();
        $admin = User::factory()->create(['is_admin' => true, 'current_team_id' => $owner->currentTeam->id]);
        $target = $this->makeEmployee($owner->currentTeam);
        $target->createToken('phone');
        $target->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [],
        ]);

        $this->actingAs($admin)->delete(route('users.destroy', $target))->assertRedirect(route('users.index'));

        $this->assertModelMissing($target);
        $this->assertDatabaseMissing('team_user', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $target->id, 'tokenable_type' => User::class]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $target->id, 'notifiable_type' => User::class]);
    }

    /**
     * Gate::before passes the admin through every check, which used to skip
     * UserPolicy::delete() entirely — an admin could delete their own account
     * from the Users screen and lock everyone out of role management.
     */
    public function test_the_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertForbidden();

        $this->assertModelExists($admin);
    }

    public function test_the_admin_cannot_delete_another_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete(route('users.destroy', $otherAdmin))->assertForbidden();

        $this->assertModelExists($otherAdmin);
    }

    /**
     * The confirmation used an inline onsubmit with the message dropped into a
     * JS string by {{ }}, which escapes for HTML, not JavaScript — an
     * apostrophe in a translation broke the script and skipped the prompt.
     */
    public function test_deleting_from_the_list_asks_for_confirmation_through_alpine(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_admin' => true]);
        $this->makeEmployee($this->makeOwner()->currentTeam);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('onsubmit=', false)
            ->assertSee('@submit="if (! confirm(', false);
    }

    public function test_the_list_offers_no_delete_button_for_admins(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_admin' => true]);
        $employee = $this->makeEmployee($this->makeOwner()->currentTeam);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('action="'.route('users.destroy', $employee->id).'"', false)
            ->assertDontSee('action="'.route('users.destroy', $admin->id).'"', false);
    }

    // ── Creating users (admin) ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function newUserPayload(Team $team, string $role): array
    {
        return [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'team_id' => $team->id,
            'role' => $role,
        ];
    }

    private function makeAdminWithWorkTeam(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $team = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);

        return [$admin, $team];
    }

    public function test_the_admin_can_create_a_manager_on_a_work_team(): void
    {
        Notification::fake();
        [$admin, $team] = $this->makeAdminWithWorkTeam();

        $this->actingAs($admin)
            ->post(route('users.store'), $this->newUserPayload($team, 'manager'))
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'new.person@example.com')->firstOrFail();
        $this->assertSame('manager', $created->teamRole($team)->key);
    }

    public function test_the_admin_cannot_put_a_new_user_on_a_personal_team(): void
    {
        [$admin] = $this->makeAdminWithWorkTeam();
        $personalTeam = $this->makeOwner()->currentTeam;

        $this->actingAs($admin)
            ->post(route('users.store'), $this->newUserPayload($personalTeam, 'employee'))
            ->assertSessionHasErrors('team_id');

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    public function test_the_admin_cannot_give_a_new_user_the_team_admin_role(): void
    {
        [$admin, $team] = $this->makeAdminWithWorkTeam();

        $this->actingAs($admin)
            ->post(route('users.store'), $this->newUserPayload($team, 'admin'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    public function test_owner_cannot_delete_a_user_on_another_owners_team(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $target = $this->makeEmployee($otherOwner->currentTeam);

        $this->actingAs($owner)->delete(route('users.destroy', $target))->assertForbidden();

        $this->assertModelExists($target);
    }

    public function test_owner_cannot_update_a_user_on_another_owners_team(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $target = $this->makeEmployee($otherOwner->currentTeam);

        $this->actingAs($owner)
            ->put(route('users.update', $target), ['name' => 'Renamed', 'email' => 'renamed@example.com'])
            ->assertForbidden();

        $this->assertSame($target->name, $target->fresh()->name);
    }

    public function test_owner_cannot_edit_a_user_on_another_owners_team(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $target = $this->makeEmployee($otherOwner->currentTeam);

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
