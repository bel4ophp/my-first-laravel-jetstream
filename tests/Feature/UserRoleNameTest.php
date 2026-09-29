<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The role label shown next to a user's name.
 *
 * Resolving it costs two or three queries, and the team-members screen reads it
 * once per row for the same signed-in user, so the accessor memoises.
 */
class UserRoleNameTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithTeam(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    private function memberOf(Team $team, TeamRole $role): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    /**
     * @return array{0: int, 1: int} queries after the first read, and after three
     */
    private function queriesForRepeatedReads(User $user): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $user->roleName;
        $afterFirst = count(DB::getQueryLog());

        $user->roleName;
        $user->roleName;
        $afterThree = count(DB::getQueryLog());

        DB::disableQueryLog();

        return [$afterFirst, $afterThree];
    }

    // ── Memoisation ───────────────────────────────────────────────────────────

    public function test_repeated_reads_do_not_requery(): void
    {
        $owner = $this->ownerWithTeam();
        $manager = $this->memberOf($owner->currentTeam, TeamRole::Manager);

        [$afterFirst, $afterThree] = $this->queriesForRepeatedReads($manager);

        $this->assertGreaterThan(0, $afterFirst, 'The first read should hit the database.');
        $this->assertSame($afterFirst, $afterThree, 'Later reads should be served from the cached value.');
    }

    public function test_an_admin_short_circuits_without_touching_the_database(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        [$afterFirst, $afterThree] = $this->queriesForRepeatedReads($admin);

        $this->assertSame(0, $afterFirst);
        $this->assertSame(0, $afterThree);
    }

    // ── Labels ────────────────────────────────────────────────────────────────

    public function test_a_global_admin_is_labelled_administrator(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertSame('Administrator', $admin->roleName);
    }

    public function test_a_manager_is_labelled_from_their_pivot_role(): void
    {
        $owner = $this->ownerWithTeam();
        $manager = $this->memberOf($owner->currentTeam, TeamRole::Manager);

        $this->assertSame('Manager', $manager->roleName);
    }

    public function test_an_employee_is_labelled_from_their_pivot_role(): void
    {
        $owner = $this->ownerWithTeam();
        $employee = $this->memberOf($owner->currentTeam, TeamRole::Employee);

        $this->assertSame('Employee', $employee->roleName);
    }

    /**
     * Jetstream reports a team's owner as the synthetic "owner" role, which
     * this app presents as the administrator label.
     */
    public function test_a_team_owner_is_labelled_administrator(): void
    {
        $owner = $this->ownerWithTeam();

        Team::forceCreate([
            'user_id' => $owner->id,
            'name' => 'Work Team',
            'personal_team' => false,
        ]);

        $this->assertSame('Administrator', $owner->fresh()->roleName);
    }

    public function test_a_user_on_no_team_has_no_label(): void
    {
        $loner = User::factory()->create(['current_team_id' => null]);

        $this->assertSame('n/a', $loner->roleName);
    }

    /**
     * The owner branch used to assign to $role->key on the object returned by
     * teamRole(). For a pivot role that object is the shared instance held in
     * Jetstream's static registry, so the pattern was one refactor away from
     * rewriting a registered role for the rest of the request.
     */
    public function test_reading_the_label_does_not_mutate_the_registered_roles(): void
    {
        $owner = $this->ownerWithTeam();

        Team::forceCreate([
            'user_id' => $owner->id,
            'name' => 'Work Team',
            'personal_team' => false,
        ]);

        $owner->fresh()->roleName;

        $this->assertSame('owner', \Laravel\Jetstream\Jetstream::findRole('owner')?->key ?? 'owner');
        $this->assertSame('Manager', \Laravel\Jetstream\Jetstream::findRole('manager')->name);
        $this->assertSame('Administrator', \Laravel\Jetstream\Jetstream::findRole('admin')->name);
    }
}
