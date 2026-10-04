<?php

namespace Tests\Feature;

use App\Livewire\Teams\TeamMemberManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveTeamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Members don't leave on their own; the admin, or a manager for their own
     * team's employees, removes people.
     */
    public function test_members_cannot_leave_teams(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['is_admin' => true]);

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(), ['role' => 'employee']
        );

        $this->actingAs($otherUser);

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->call('leaveTeam')
            ->assertForbidden();

        $this->assertCount(1, $user->currentTeam->fresh()->users);
    }

    public function test_team_owners_cant_leave_their_own_team(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create(['is_admin' => true]));

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->call('leaveTeam')
            ->assertHasErrors(['team']);

        $this->assertNotNull($user->currentTeam->fresh());
    }
}
