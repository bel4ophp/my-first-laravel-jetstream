<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\View\Components\Welcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's welcome card: team owners (the admin) get the all-teams and
 * current-team stats side by side, everyone else their own card.
 *
 * Whether the user owns a team used to be queried from inside the template;
 * the component class works it out instead.
 */
class WelcomeComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_owner_is_shown_the_all_teams_stats(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $team = Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($admin);

        $this->assertTrue((new Welcome)->isOwner);
        $this->component(Welcome::class)->assertSee('All Teams');
    }

    public function test_a_team_member_is_shown_their_own_card(): void
    {
        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $employee = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($employee, ['role' => 'employee']);

        $this->actingAs($employee);

        $this->assertFalse((new Welcome)->isOwner);
        $this->component(Welcome::class)->assertDontSee('All Teams');
    }

    public function test_the_template_runs_no_query_of_its_own(): void
    {
        $this->assertStringNotContainsString(
            'ownedTeams()',
            file_get_contents(resource_path('views/components/welcome.blade.php')),
        );
    }
}
