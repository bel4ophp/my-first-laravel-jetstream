<?php

namespace Tests\Feature;

use App\Actions\Jetstream\ProvisionTeamMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Creating an account straight onto a team — shared by the Users screen and
 * the team-invite flow, and the path the API will use.
 */
class ProvisionTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    private function workTeam(): Team
    {
        $admin = User::factory()->create(['is_admin' => true]);

        return Team::factory()->create(['user_id' => $admin->id, 'personal_team' => false]);
    }

    public function test_it_creates_the_member_on_the_team_and_sends_a_set_password_link(): void
    {
        Notification::fake();
        $team = $this->workTeam();

        $member = app(ProvisionTeamMember::class)->provision($team, 'Ana Petrović', 'ana@example.com', 'employee');

        $this->assertSame('Ana Petrović', $member->name);
        $this->assertSame('employee', $member->teamRole($team)->key);
        $this->assertTrue($member->currentTeam->is($team));
        Notification::assertSentTo($member, ResetPassword::class);
    }

    /**
     * The Users screen used to create the account and attach the team as two
     * separate writes, so a failed attach left a user on no team.
     */
    public function test_a_failed_team_attach_leaves_no_account_behind(): void
    {
        Notification::fake();
        $unsavedTeam = Team::factory()->make(['personal_team' => false]);

        try {
            app(ProvisionTeamMember::class)->provision($unsavedTeam, 'Ana Petrović', 'ana@example.com', 'employee');
            $this->fail('Attaching to an unsaved team should have failed.');
        } catch (QueryException) {
            // Expected: team_user.team_id cannot be null.
        }

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        Notification::assertNothingSent();
    }
}
