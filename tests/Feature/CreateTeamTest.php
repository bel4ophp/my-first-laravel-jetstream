<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\CreateTeamForm;
use Livewire\Livewire;
use Tests\TestCase;

class CreateTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_can_be_created(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create(['is_admin' => true]));

        Livewire::test(CreateTeamForm::class)
            ->set(['state' => ['name' => 'Test Team']])
            ->call('createTeam');

        $this->assertCount(2, $user->fresh()->ownedTeams);
        $this->assertEquals('Test Team', $user->fresh()->ownedTeams()->latest('id')->first()->name);
    }

    /**
     * The creator owns the team via teams.user_id and is not a pivot member —
     * the shape TeamSeeder produces and allUsers() assumes. A second creation
     * path used to attach the creator as a manager instead, which produced a
     * different shape and put them on a second team.
     */
    public function test_the_creator_owns_the_team_without_joining_it_as_a_member(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create(['is_admin' => true]));

        Livewire::test(CreateTeamForm::class)
            ->set(['state' => ['name' => 'Test Team']])
            ->call('createTeam');

        $team = $user->fresh()->ownedTeams()->latest('id')->first();

        $this->assertTrue($team->owner->is($user));
        $this->assertDatabaseMissing('team_user', ['team_id' => $team->id, 'user_id' => $user->id]);

        // Ownership alone still grants full access to the new team.
        $this->assertTrue($user->fresh()->hasTeamPermission($team, 'update-time-entries'));
    }

    public function test_a_new_team_starts_with_no_manager(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create(['is_admin' => true]));

        Livewire::test(CreateTeamForm::class)
            ->set(['state' => ['name' => 'Test Team']])
            ->call('createTeam');

        $team = $user->fresh()->ownedTeams()->latest('id')->first();

        $this->assertNull($team->manager());
        $this->assertFalse($team->hasManagerBesides());
    }

    /**
     * Guards the one-team-per-user invariant at its last remaining hole.
     *
     * The removed branch only fired when the creator was already a manager
     * somewhere, and it attached them to the new team's pivot — handing them a
     * second membership that BelongsToNoOtherTeam forbids everywhere else.
     */
    public function test_a_creator_who_manages_another_team_does_not_join_the_new_one(): void
    {
        $creator = User::factory()->withPersonalTeam()->create(['is_admin' => true]);

        // Legacy-shaped data: owns their current team, but also holds a manager
        // role on someone else's. That combination is what reached the branch —
        // ownership passes the create gate, the pivot role made isTeamManager() true.
        $otherOwner = User::factory()->withPersonalTeam()->create();
        $otherOwner->currentTeam->users()->attach($creator, ['role' => TeamRole::Manager->value]);

        $this->assertTrue($creator->fresh()->isTeamManager());

        $this->actingAs($creator->fresh());

        Livewire::test(CreateTeamForm::class)
            ->set(['state' => ['name' => 'Second Team']])
            ->call('createTeam');

        $newTeam = $creator->fresh()->ownedTeams()->where('name', 'Second Team')->first();

        $this->assertNotNull($newTeam);
        $this->assertDatabaseMissing('team_user', ['team_id' => $newTeam->id, 'user_id' => $creator->id]);

        // Still only the one pre-existing membership.
        $this->assertSame(1, $creator->fresh()->teams()->count());
    }
}
