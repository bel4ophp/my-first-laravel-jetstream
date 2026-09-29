<?php

namespace Tests\Feature;

use App\Actions\Jetstream\InviteTeamMember;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Laravel\Jetstream\Mail\TeamInvitation;
use Livewire\Livewire;
use Tests\TestCase;

class InviteTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_members_can_be_invited_to_team(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Mail::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        // The app only sends an invitation when the email belongs to an
        // existing user; unknown emails are provisioned directly instead.
        $invited = User::factory()->create();

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('addTeamMemberForm', [
                'email' => $invited->email,
                'role' => 'employee',
            ])->call('addTeamMember');

        Mail::assertSent(TeamInvitation::class);

        $this->assertCount(1, $user->currentTeam->fresh()->teamInvitations);
    }

    public function test_team_member_invitations_can_be_cancelled(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Mail::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $invited = User::factory()->create();

        // Add the team member...
        $component = Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('addTeamMemberForm', [
                'email' => $invited->email,
                'role' => 'employee',
            ])->call('addTeamMember');

        $invitationId = $user->currentTeam->fresh()->teamInvitations->first()->id;

        // Cancel the team invitation...
        $component->call('cancelTeamInvitation', $invitationId);

        $this->assertCount(0, $user->currentTeam->fresh()->teamInvitations);
    }

    public function test_inviting_an_unknown_email_provisions_a_user_and_sends_a_password_reset(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Notification::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('addTeamMemberForm', [
                'email' => 'newcomer@example.com',
                'role' => 'employee',
            ])->call('addTeamMember');

        // No invitation row — the account is created and attached directly.
        $this->assertCount(0, $user->currentTeam->fresh()->teamInvitations);

        $newUser = User::where('email', 'newcomer@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($user->currentTeam->fresh()->hasUser($newUser));
        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_a_provisioned_member_gets_a_readable_placeholder_name(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Notification::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('addTeamMemberForm', [
                'email' => 'ada.lovelace@example.com',
                'role' => 'employee',
            ])->call('addTeamMember');

        $this->assertSame('Ada Lovelace', User::where('email', 'ada.lovelace@example.com')->value('name'));
    }

    /**
     * The whole flow used to sit in one try/catch whose handler assumed "no
     * such user". A mail failure for an *existing* address therefore fell
     * through to account creation, hit the unique-email constraint, and
     * surfaced as a 500 with an orphaned invitation row.
     */
    public function test_a_mail_failure_does_not_try_to_create_a_duplicate_account(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP is down'));

        try {
            app(InviteTeamMember::class)->invite(
                $user, $user->currentTeam, $existing->email, 'employee'
            );
            $this->fail('Expected the mail failure to surface.');
        } catch (\RuntimeException $e) {
            // The transport error reaches the caller instead of being
            // reinterpreted as a missing account.
            $this->assertSame('SMTP is down', $e->getMessage());
        }

        // Exactly one account for that address — no duplicate was attempted.
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }
}