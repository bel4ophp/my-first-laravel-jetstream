<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The month/year scopes express their filter as a date range so the
 * (user_id, work_day) index stays usable. Ranges are where off-by-one errors
 * live, so the boundaries are pinned here.
 */
class TimeEntryScopesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function entryOn(string $date): TimeEntry
    {
        return TimeEntry::factory()->forDay($date)->create(['user_id' => $this->user->id]);
    }

    public function test_for_month_includes_the_first_and_last_day(): void
    {
        $first = $this->entryOn('2026-04-01');
        $last = $this->entryOn('2026-04-30');

        $ids = TimeEntry::forMonth(2026, 4)->pluck('id');

        $this->assertTrue($ids->contains($first->id));
        $this->assertTrue($ids->contains($last->id));
    }

    public function test_for_month_excludes_the_adjacent_days(): void
    {
        $before = $this->entryOn('2026-03-31');
        $after = $this->entryOn('2026-05-01');

        $ids = TimeEntry::forMonth(2026, 4)->pluck('id');

        $this->assertFalse($ids->contains($before->id));
        $this->assertFalse($ids->contains($after->id));
    }

    public function test_for_month_handles_february_in_a_leap_year(): void
    {
        $leapDay = $this->entryOn('2024-02-29');

        $this->assertTrue(TimeEntry::forMonth(2024, 2)->pluck('id')->contains($leapDay->id));
    }

    public function test_for_month_handles_february_in_a_common_year(): void
    {
        $lastDay = $this->entryOn('2026-02-28');
        $marchFirst = $this->entryOn('2026-03-01');

        $ids = TimeEntry::forMonth(2026, 2)->pluck('id');

        $this->assertTrue($ids->contains($lastDay->id));
        $this->assertFalse($ids->contains($marchFirst->id));
    }

    public function test_for_month_handles_december(): void
    {
        $newYearsEve = $this->entryOn('2026-12-31');
        $newYearsDay = $this->entryOn('2027-01-01');

        $ids = TimeEntry::forMonth(2026, 12)->pluck('id');

        $this->assertTrue($ids->contains($newYearsEve->id));
        $this->assertFalse($ids->contains($newYearsDay->id));
    }

    public function test_for_year_spans_january_first_to_december_thirty_first(): void
    {
        $first = $this->entryOn('2026-01-01');
        $last = $this->entryOn('2026-12-31');
        $next = $this->entryOn('2027-01-01');

        $ids = TimeEntry::forYear(2026)->pluck('id');

        $this->assertTrue($ids->contains($first->id));
        $this->assertTrue($ids->contains($last->id));
        $this->assertFalse($ids->contains($next->id));
    }

    public function test_on_day_matches_exactly_one_day(): void
    {
        $target = $this->entryOn('2026-04-15');
        $dayBefore = $this->entryOn('2026-04-14');

        $ids = TimeEntry::onDay('2026-04-15')->pluck('id');

        $this->assertTrue($ids->contains($target->id));
        $this->assertFalse($ids->contains($dayBefore->id));
    }

    public function test_active_returns_only_entries_without_a_clock_out(): void
    {
        $closed = $this->entryOn('2026-04-15');
        $open = $this->entryOn('2026-04-15');
        $open->update(['clock_out' => null, 'worked_minutes' => null]);

        $ids = TimeEntry::active()->pluck('id');

        $this->assertTrue($ids->contains($open->id));
        $this->assertFalse($ids->contains($closed->id));
    }
}
