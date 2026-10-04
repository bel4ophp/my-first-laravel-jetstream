<?php

namespace Tests\Feature;

use App\Events\UserClockedInEvent;
use App\Livewire\TimeTracker;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard clock-in/clock-out widget.
 *
 * One shift per day: clocking in starts today's entry, clocking out closes it,
 * and nothing reopens or moves it afterwards. The component's public state
 * ($isRunning and friends) is editable from the browser, so every rule here is
 * checked against the database rather than against that state.
 */
class TimeTrackerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 08:00:00');
        Event::fake([UserClockedInEvent::class]);

        $this->user = User::factory()->withPersonalTeam()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function tracker()
    {
        return Livewire::actingAs($this->user)->test(TimeTracker::class);
    }

    private function todaysEntries()
    {
        return TimeEntry::where('user_id', $this->user->id)->onDay(today())->get();
    }

    // ── Clocking in ───────────────────────────────────────────────────────────

    public function test_clocking_in_starts_todays_entry(): void
    {
        $this->tracker()
            ->call('clockIn')
            ->assertSet('isRunning', true);

        $entry = $this->todaysEntries()->sole();
        $this->assertTrue($entry->clock_in->equalTo(now()));
        $this->assertNull($entry->clock_out);
        Event::assertDispatchedTimes(UserClockedInEvent::class, 1);
    }

    public function test_clocking_in_twice_keeps_one_entry_and_one_notification(): void
    {
        $tracker = $this->tracker()->call('clockIn');

        Carbon::setTestNow('2026-06-15 08:05:00');
        $tracker->call('clockIn');

        $this->assertCount(1, $this->todaysEntries());
        $this->assertSame('08:00', $this->todaysEntries()->first()->clockInFormatted());
        Event::assertDispatchedTimes(UserClockedInEvent::class, 1);
    }

    /**
     * Forging $isRunning used to skip the "already running" guard and move the
     * start of a running shift to now.
     */
    public function test_a_forged_running_flag_cannot_move_a_running_shifts_start(): void
    {
        $tracker = $this->tracker()->call('clockIn');

        Carbon::setTestNow('2026-06-15 11:00:00');
        $tracker->set('isRunning', false)->call('clockIn');

        $this->assertSame('08:00', $this->todaysEntries()->sole()->clockInFormatted());
        Event::assertDispatchedTimes(UserClockedInEvent::class, 1);
    }

    /**
     * Clocking in after clocking out used to overwrite clock_in and leave the
     * old clock_out in place — a shift ending before it started.
     */
    public function test_clocking_in_again_after_clocking_out_is_refused(): void
    {
        $tracker = $this->tracker()->call('clockIn');

        Carbon::setTestNow('2026-06-15 16:00:00');
        $tracker->call('clockOut');

        Carbon::setTestNow('2026-06-15 17:00:00');
        $tracker->call('clockIn');

        $entry = $this->todaysEntries()->sole();
        $this->assertSame('08:00', $entry->clockInFormatted());
        $this->assertSame('16:00', $entry->clockOutFormatted());
        Event::assertDispatchedTimes(UserClockedInEvent::class, 1);
    }

    // ── Clocking out ──────────────────────────────────────────────────────────

    public function test_clocking_out_closes_todays_entry(): void
    {
        $tracker = $this->tracker()->call('clockIn');

        Carbon::setTestNow('2026-06-15 15:30:00');
        $tracker->call('clockOut')->assertSet('isRunning', false);

        $entry = $this->todaysEntries()->sole();
        $this->assertSame('15:30', $entry->clockOutFormatted());
        $this->assertSame(450, $entry->worked_minutes);
    }

    /**
     * Forging $isRunning used to let a finished shift be clocked out again,
     * pushing its end — and its worked minutes — later.
     */
    public function test_a_forged_running_flag_cannot_extend_a_finished_shift(): void
    {
        $tracker = $this->tracker()->call('clockIn');

        Carbon::setTestNow('2026-06-15 16:00:00');
        $tracker->call('clockOut');

        Carbon::setTestNow('2026-06-15 20:00:00');
        $tracker->set('isRunning', true)->call('clockOut');

        $entry = $this->todaysEntries()->sole();
        $this->assertSame('16:00', $entry->clockOutFormatted());
        $this->assertSame(480, $entry->worked_minutes);
    }

    /**
     * The dashboard timer clocks out automatically, but a browser's clock and
     * timers can't be trusted — so the server caps the shift, the same rule the
     * scheduled command applies to shifts nobody clocked out.
     */
    public function test_clocking_out_past_the_maximum_shift_records_exactly_the_maximum(): void
    {
        $tracker = $this->tracker()->call('clockIn');

        Carbon::setTestNow('2026-06-15 16:07:00');
        $tracker->call('clockOut');

        $entry = $this->todaysEntries()->sole();
        $this->assertSame('16:00', $entry->clockOutFormatted());
        $this->assertSame(480, $entry->worked_minutes);
    }

    public function test_the_maximum_shift_comes_from_config(): void
    {
        config(['attendance.max_shift_hours' => 6]);

        $tracker = $this->tracker()->call('clockIn');

        Carbon::setTestNow('2026-06-15 15:00:00');
        $tracker->call('clockOut');

        $this->assertSame(360, $this->todaysEntries()->sole()->worked_minutes);
    }

    public function test_the_timer_is_given_the_maximum_shift_and_the_server_time(): void
    {
        $this->tracker()
            ->assertSeeHtml('maxShiftSeconds')
            ->assertSee('28800')
            ->assertSee((string) now()->getTimestampMs());
    }

    public function test_clocking_out_without_clocking_in_does_nothing(): void
    {
        $this->tracker()
            ->set('isRunning', true)
            ->call('clockOut')
            ->assertSet('isRunning', false);

        $this->assertCount(0, $this->todaysEntries());
    }

    // ── Initial state ─────────────────────────────────────────────────────────

    public function test_the_widget_reflects_a_shift_already_running(): void
    {
        TimeEntry::factory()->active()->create([
            'user_id' => $this->user->id,
            'work_day' => today()->toDateString(),
            'clock_in' => now()->subHour(),
        ]);

        $this->tracker()->assertSet('isRunning', true);
    }
}
