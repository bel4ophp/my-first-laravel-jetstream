<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who may shape teams and their membership.
 *
 * Only the global admin creates, renames and deletes teams, changes roles and
 * invites managers. A manager invites employees to, and removes employees from,
 * their own team only. Nobody leaves a team on their own; the admin removes
 * people. Employees manage nothing.
 */
class TeamAccessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    private User $manager;

    private User $employee;

    private Team $otherTeam;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->team = $this->teamOwnedByAdmin();
        $this->admin->forceFill(['current_team_id' => $this->team->id])->save();

        $this->manager = $this->memberOf($this->team, TeamRole::Manager);
        $this->employee = $this->memberOf($this->team, TeamRole::Employee);

        $this->otherTeam = $this->teamOwnedByAdmin();
        $this->otherEmployee = $this->memberOf($this->otherTeam, TeamRole::Employee);
    }

    private function teamOwnedByAdmin(): Team
    {
        return Team::factory()->create(['user_id' => $this->admin->id, 'personal_team' => false]);
    }

    private function memberOf(Team $team, TeamRole $role): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function invite(User $actor, Team $team, string $role): void
    {
        app(InvitesTeamMembers::class)->invite($actor, $team, fake()->unique()->safeEmail(), $role);
    }

    private function changeRole(User $actor, User $member, string $role): void
    {
        app(UpdateTeamMemberRole::class)->update($actor, $this->team, $member->id, $role);
    }

    private function remove(User $actor, Team $team, User $member): void
    {
        app(RemovesTeamMembers::class)->remove($actor, $team, $member);
    }

    // ── Creating, renaming and deleting teams ────────────────────────────────

    public function test_the_admin_can_create_a_team(): void
    {
        $team = app(CreatesTeams::class)->create($this->admin, ['name' => 'Omega']);

        $this->assertTrue($team->owner->is($this->admin));
    }

    public function test_a_manager_cannot_create_a_team(): void
    {
        $this->expectException(AuthorizationException::class);

        app(CreatesTeams::class)->create($this->manager, ['name' => 'Omega']);
    }

    public function test_the_admin_can_rename_a_team(): void
    {
        app(UpdatesTeamNames::class)->update($this->admin, $this->team, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $this->team->fresh()->name);
    }

    public function test_a_manager_cannot_rename_their_team(): void
    {
        $originalName = $this->team->name;

        try {
            app(UpdatesTeamNames::class)->update($this->manager, $this->team, ['name' => 'Renamed']);
            $this->fail('A manager renamed their team.');
        } catch (AuthorizationException) {
            $this->assertSame($originalName, $this->team->fresh()->name);
        }
    }

    public function test_only_the_admin_can_delete_a_team(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $this->team));
        $this->assertTrue(Gate::forUser($this->manager)->denies('delete', $this->team));
        $this->assertTrue(Gate::forUser($this->employee)->denies('delete', $this->team));
    }

    // ── Inviting ──────────────────────────────────────────────────────────────

    public function test_the_admin_can_invite_a_manager(): void
    {
        $this->invite($this->admin, $this->otherTeam, TeamRole::Manager->value);

        $this->assertNotNull($this->otherTeam->fresh()->manager());
    }

    public function test_a_manager_can_invite_an_employee_to_their_own_team(): void
    {
        $this->invite($this->manager, $this->team, TeamRole::Employee->value);

        $this->assertCount(3, $this->team->fresh()->users);
    }

    public function test_a_manager_cannot_invite_someone_as_a_team_admin(): void
    {
        $this->expectException(ValidationException::class);

        $this->invite($this->manager, $this->team, TeamRole::Admin->value);
    }

    public function test_the_admin_cannot_hand_out_the_team_admin_role_either(): void
    {
        $this->expectException(ValidationException::class);

        $this->invite($this->admin, $this->otherTeam, TeamRole::Admin->value);
    }

    public function test_a_manager_cannot_invite_to_another_team(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->invite($this->manager, $this->otherTeam, TeamRole::Employee->value);
    }

    public function test_an_employee_cannot_invite(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->invite($this->employee, $this->team, TeamRole::Employee->value);
    }

    public function test_an_employee_cannot_cancel_an_invitation(): void
    {
        $invitation = $this->team->teamInvitations()->create([
            'email' => 'pending@example.com',
            'role' => TeamRole::Employee->value,
        ]);

        Livewire::actingAs($this->employee)
            ->test('teams.team-member-manager', ['team' => $this->team])
            ->call('cancelTeamInvitation', $invitation->id)
            ->assertForbidden();

        $this->assertModelExists($invitation);
    }

    public function test_a_manager_can_cancel_an_invitation_to_their_own_team(): void
    {
        $invitation = $this->team->teamInvitations()->create([
            'email' => 'pending@example.com',
            'role' => TeamRole::Employee->value,
        ]);

        Livewire::actingAs($this->manager)
            ->test('teams.team-member-manager', ['team' => $this->team])
            ->call('cancelTeamInvitation', $invitation->id);

        $this->assertModelMissing($invitation);
    }

    /**
     * The modals bind through $wire.$entangle() rather than @entangle, keeping
     * the .live modifier their callers ask for.
     */
    public function test_the_team_page_modals_entangle_through_wire(): void
    {
        Livewire::actingAs($this->admin)
            ->test('teams.team-member-manager', ['team' => $this->team])
            ->assertSeeHtml("\$wire.\$entangle('currentlyManagingRole', true)")
            ->assertSeeHtml("\$wire.\$entangle('confirmingTeamMemberRemoval', true)")
            ->assertDontSeeHtml('.entangle(');
    }

    // ── Changing roles ────────────────────────────────────────────────────────

    public function test_the_admin_can_change_a_members_role(): void
    {
        $this->changeRole($this->admin, $this->manager, TeamRole::Employee->value);

        $this->assertSame(TeamRole::Employee->value, $this->manager->fresh()->teamRole($this->team)->key);
    }

    public function test_a_manager_cannot_change_an_employees_role(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->changeRole($this->manager, $this->employee, TeamRole::Manager->value);
    }

    public function test_a_manager_cannot_change_their_own_role(): void
    {
        try {
            $this->changeRole($this->manager, $this->manager, TeamRole::Admin->value);
            $this->fail('A manager changed their own role.');
        } catch (AuthorizationException) {
            $this->assertSame(TeamRole::Manager->value, $this->manager->fresh()->teamRole($this->team)->key);
        }
    }

    public function test_the_admin_cannot_assign_the_team_admin_role(): void
    {
        $this->expectException(ValidationException::class);

        $this->changeRole($this->admin, $this->employee, TeamRole::Admin->value);
    }

    // ── Removing and leaving ──────────────────────────────────────────────────

    public function test_the_admin_can_remove_a_manager(): void
    {
        $this->remove($this->admin, $this->team, $this->manager);

        $this->assertFalse($this->manager->fresh()->belongsToTeam($this->team));
    }

    public function test_a_manager_can_remove_an_employee_from_their_own_team(): void
    {
        $this->remove($this->manager, $this->team, $this->employee);

        $this->assertFalse($this->employee->fresh()->belongsToTeam($this->team));
    }

    public function test_a_manager_cannot_remove_an_employee_from_another_team(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->remove($this->manager, $this->otherTeam, $this->otherEmployee);
    }

    public function test_a_manager_cannot_leave_their_team(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->remove($this->manager, $this->team, $this->manager);
    }

    public function test_an_employee_cannot_leave_their_team(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->remove($this->employee, $this->team, $this->employee);
    }

    public function test_an_employee_cannot_remove_another_member(): void
    {
        $colleague = $this->memberOf($this->team, TeamRole::Employee);

        $this->expectException(AuthorizationException::class);

        $this->remove($this->employee, $this->team, $colleague);
    }
}
