<?php

namespace Tests\Feature;

use App\Livewire\AttendanceDayPanel;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Up to two initials, multibyte-safe.
 *
 * substr()/strtoupper() work on bytes, so a name starting with "Đ" or "Š"
 * produced half a character, and the attendance panel's own copy of the logic
 * read $segment[0], which errored on a double space in a name.
 */
class UserInitialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initials_keep_non_ascii_letters_whole(): void
    {
        $user = User::factory()->make(['name' => 'Đorđe Šaković']);

        $this->assertSame('ĐŠ', $user->initials);
    }

    public function test_lowercase_non_ascii_letters_are_uppercased(): void
    {
        $user = User::factory()->make(['name' => 'željko čolić']);

        $this->assertSame('ŽČ', $user->initials);
    }

    public function test_extra_spaces_and_extra_names_are_ignored(): void
    {
        $user = User::factory()->make(['name' => '  Ana   Marija  Petrović ']);

        $this->assertSame('AM', $user->initials);
    }

    public function test_the_attendance_panel_shows_the_same_initials(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager, ['role' => 'manager']);
        $employee = User::factory()->create(['name' => 'Đorđe  Šaković', 'current_team_id' => $team->id]);
        $team->users()->attach($employee, ['role' => 'employee']);

        TimeEntry::factory()->forDay('2026-06-15')->create(['user_id' => $employee->id]);

        Livewire::actingAs($manager)
            ->test(AttendanceDayPanel::class, ['date' => '2026-06-15'])
            ->assertOk()
            ->assertSee('ĐŠ');

        Carbon::setTestNow();
    }
}
