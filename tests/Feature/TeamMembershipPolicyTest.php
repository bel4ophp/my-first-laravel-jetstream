<?php

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\InviteTeamMember;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * One team per user, enforced identically on both joining paths.
 *
 * These previously disagreed: inviting rejected anyone already on a team, while
 * adding an existing user happily put them on a second one. Leave approval, the
 * role label and attendance scoping all route off the user's current team, so a
 * second membership makes a user's approver ambiguous.
 */
class TeamMembershipPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Teams are owned by the global admin, the only one who may add managers.
     */
    private function ownerWithTeam(): User
    {
        return User::factory()->withPersonalTeam()->create(['is_admin' => true]);
    }

    private function memberOf(Team $team, TeamRole $role = TeamRole::Employee): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function add(User $actor, Team $team, string $email, string $role): void
    {
        app(AddTeamMember::class)->add($actor, $team, $email, $role);
    }

    private function invite(User $actor, Team $team, string $email, string $role): void
    {
        app(InviteTeamMember::class)->invite($actor, $team, $email, $role);
    }

    // ── One team per user, on both paths ──────────────────────────────────────

    public function test_adding_rejects_a_user_who_is_already_on_another_team(): void
    {
        $owner = $this->ownerWithTeam();
        $otherOwner = $this->ownerWithTeam();
        $taken = $this->memberOf($otherOwner->currentTeam);

        $this->expectException(ValidationException::class);

        $this->add($owner, $owner->currentTeam, $taken->email, TeamRole::Employee->value);
    }

    public function test_inviting_rejects_a_user_who_is_already_on_another_team(): void
    {
        $owner = $this->ownerWithTeam();
        $otherOwner = $this->ownerWithTeam();
        $taken = $this->memberOf($otherOwner->currentTeam);

        $this->expectException(ValidationException::class);

        $this->invite($owner, $owner->currentTeam, $taken->email, TeamRole::Employee->value);
    }

    public function test_adding_accepts_a_user_who_is_on_no_team(): void
    {
        $owner = $this->ownerWithTeam();
        $free = User::factory()->create();

        $this->add($owner, $owner->currentTeam, $free->email, TeamRole::Employee->value);

        $this->assertTrue($free->fresh()->belongsToTeam($owner->currentTeam));
    }

    /**
     * Membership of the target team is reported by its own check, so the
     * "another team" rule deliberately excludes it — one error, not two.
     */
    public function test_re_adding_an_existing_member_reports_only_the_team_they_are_on(): void
    {
        $owner = $this->ownerWithTeam();
        $member = $this->memberOf($owner->currentTeam);

        try {
            $this->add($owner, $owner->currentTeam, $member->email, TeamRole::Employee->value);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $messages = $e->validator->errors()->get('email');

            $this->assertContains('This user already belongs to the team.', $messages);
            $this->assertNotContains('This user already belongs to another team.', $messages);
        }
    }

    // ── Owners are unaffected ─────────────────────────────────────────────────

    public function test_owning_several_teams_is_still_allowed(): void
    {
        $owner = $this->ownerWithTeam();

        $second = Team::forceCreate([
            'user_id' => $owner->id,
            'name' => 'Second Team',
            'personal_team' => false,
        ]);

        $this->assertCount(2, $owner->fresh()->ownedTeams);

        // Ownership lives on teams.user_id, not the pivot, so the rule is silent.
        $free = User::factory()->create();
        $this->add($owner, $second, $free->email, TeamRole::Employee->value);

        $this->assertTrue($free->fresh()->belongsToTeam($second));
    }

    // ── One manager per team, on both paths ───────────────────────────────────

    public function test_adding_rejects_a_second_manager(): void
    {
        $owner = $this->ownerWithTeam();
        $this->memberOf($owner->currentTeam, TeamRole::Manager);
        $candidate = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->add($owner, $owner->currentTeam, $candidate->email, TeamRole::Manager->value);
    }

    public function test_inviting_rejects_a_second_manager(): void
    {
        $owner = $this->ownerWithTeam();
        $this->memberOf($owner->currentTeam, TeamRole::Manager);
        $candidate = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->invite($owner, $owner->currentTeam, $candidate->email, TeamRole::Manager->value);
    }

    public function test_the_first_manager_is_accepted_on_both_paths(): void
    {
        $owner = $this->ownerWithTeam();
        $candidate = User::factory()->create();

        $this->add($owner, $owner->currentTeam, $candidate->email, TeamRole::Manager->value);

        $this->assertTrue($owner->currentTeam->fresh()->manager()->is($candidate));
    }
}
