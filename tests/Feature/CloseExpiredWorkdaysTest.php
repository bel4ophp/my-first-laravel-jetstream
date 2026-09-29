<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The command used raw DATE_ADD/TIMESTAMPDIFF, which only ran on MySQL and so
 * could never be exercised against the suite's SQLite database. These tests
 * exist because the rewrite made it testable.
 */
class CloseExpiredWorkdaysTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-10 18:00:00');

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function openEntryClockedInAt(string $clockIn): TimeEntry
    {
        return TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'work_day' => Carbon::parse($clockIn)->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => null,
            'worked_minutes' => null,
        ]);
    }

    public function test_it_closes_a_shift_left_open_beyond_eight_hours(): void
    {
        $entry = $this->openEntryClockedInAt('2026-06-10 08:00:00');

        $this->artisan('app:close-expired-workdays')->assertSuccessful();

        $entry->refresh();

        $this->assertSame('2026-06-10 16:00:00', $entry->clock_out->toDateTimeString());
        $this->assertSame(480, $entry->worked_minutes);
    }

    public function test_it_leaves_a_shift_that_is_still_within_eight_hours(): void
    {
        $entry = $this->openEntryClockedInAt('2026-06-10 12:00:00');

        $this->artisan('app:close-expired-workdays')->assertSuccessful();

        $entry->refresh();

        $this->assertNull($entry->clock_out);
        $this->assertNull($entry->worked_minutes);
    }

    public function test_it_leaves_already_closed_entries_untouched(): void
    {
        $entry = TimeEntry::factory()->forDay('2026-06-01')->create(['user_id' => $this->user->id]);
        $originalClockOut = $entry->clock_out->toDateTimeString();

        $this->artisan('app:close-expired-workdays')->assertSuccessful();

        $this->assertSame($originalClockOut, $entry->fresh()->clock_out->toDateTimeString());
    }

    public function test_it_closes_every_expired_entry_and_reports_the_count(): void
    {
        $this->openEntryClockedInAt('2026-06-09 08:00:00');
        $this->openEntryClockedInAt('2026-06-08 09:00:00');
        $this->openEntryClockedInAt('2026-06-10 17:30:00'); // still running

        $this->artisan('app:close-expired-workdays')
            ->expectsOutputToContain('Closed 2 expired workday(s).')
            ->assertSuccessful();

        $this->assertSame(1, TimeEntry::whereNull('clock_out')->count());
    }

    public function test_it_succeeds_when_there_is_nothing_to_close(): void
    {
        $this->artisan('app:close-expired-workdays')
            ->expectsOutputToContain('Closed 0 expired workday(s).')
            ->assertSuccessful();
    }
}
