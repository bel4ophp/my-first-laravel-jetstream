<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Livewire\AttendanceCalendar;
use App\Livewire\AttendanceDayPanel;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportEvents\SupportEvents;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Behavioural contract for the calendar shell: month navigation, day selection
 * and the event wiring to AttendanceDayPanel, which owns the per-day detail and
 * is covered by AttendanceDayPanelTest.
 *
 * Viewing/scoping is covered by AttendanceCalendarCanViewTest and
 * AttendanceCalendarSelectableUsersTest.
 *
 * Reference month: June 2026 starts on a Monday and has 30 days. "Now" is
 * pinned inside it so the never-navigate-past-the-current-month rule applies.
 */
class AttendanceCalendarInteractionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $employee;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 10:00:00');

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->team = $this->owner->currentTeam;

        $this->manager = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($this->manager, ['role' => TeamRole::Manager->value]);

        $this->employee = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($this->employee, ['role' => TeamRole::Employee->value]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function calendar(?User $as = null, int $year = 2026, int $month = 6)
    {
        return Livewire::actingAs($as ?? $this->manager)
            ->test(AttendanceCalendar::class, ['year' => $year, 'month' => $month]);
    }

    private function entryFor(User $user, string $workDay): TimeEntry
    {
        return TimeEntry::factory()->forDay($workDay)->create(['user_id' => $user->id]);
    }

    // ── Query-string period ───────────────────────────────────────────────────

    private function calendarFromUrl(array $query)
    {
        return Livewire::withQueryParams($query)
            ->actingAs($this->manager)
            ->test(AttendanceCalendar::class);
    }

    /**
     * ?month=13 used to roll silently into January of the following year.
     */
    public function test_an_out_of_range_month_in_the_url_falls_back_to_the_current_month(): void
    {
        $this->calendarFromUrl(['year' => 2025, 'month' => 13])
            ->assertSet('year', 2025)
            ->assertSet('month', 6);
    }

    public function test_a_year_before_the_first_selectable_one_falls_back_to_the_current_year(): void
    {
        $this->calendarFromUrl(['year' => 1999, 'month' => 3])
            ->assertSet('year', 2026)
            ->assertSet('month', 3);
    }

    public function test_a_future_year_in_the_url_falls_back_to_the_current_month(): void
    {
        $this->calendarFromUrl(['year' => 2030, 'month' => 2])
            ->assertSet('year', 2026)
            ->assertSet('month', 2);
    }

    public function test_an_invalid_month_set_from_the_page_is_corrected(): void
    {
        $this->calendar()
            ->set('month', 0)
            ->assertSet('month', 6);
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public function test_prev_month_steps_back_and_clears_the_selected_day(): void
    {
        $this->calendar()
            ->call('selectDay', 10)
            ->assertSet('selectedDay', 10)
            ->call('prevMonth')
            ->assertSet('year', 2026)
            ->assertSet('month', 5)
            ->assertSet('selectedDay', null);
    }

    public function test_prev_month_rolls_back_over_a_year_boundary(): void
    {
        $this->calendar(year: 2026, month: 1)
            ->call('prevMonth')
            ->assertSet('year', 2025)
            ->assertSet('month', 12);
    }

    public function test_next_month_advances_from_a_past_month(): void
    {
        $this->calendar(year: 2026, month: 4)
            ->call('nextMonth')
            ->assertSet('year', 2026)
            ->assertSet('month', 5);
    }

    public function test_next_month_refuses_to_move_past_the_current_month(): void
    {
        $this->calendar(year: 2026, month: 6)
            ->call('nextMonth')
            ->assertSet('year', 2026)
            ->assertSet('month', 6);
    }

    public function test_choosing_a_future_month_in_the_current_year_clamps_to_now(): void
    {
        $this->calendar()
            ->set('month', 11)
            ->assertSet('month', 6);
    }

    public function test_choosing_a_past_month_is_left_alone(): void
    {
        $this->calendar()
            ->set('month', 2)
            ->assertSet('month', 2);
    }

    public function test_month_label_and_length(): void
    {
        $this->calendar()
            ->assertSet('calendarLabel', 'June 2026')
            ->assertSet('daysInMonth', 30)
            ->assertSet('isCurrentMonth', true);
    }

    public function test_a_month_starting_on_monday_needs_no_leading_blanks(): void
    {
        $this->calendar(year: 2026, month: 6)->assertSet('firstDayOffset', 0);
    }

    public function test_a_month_starting_on_sunday_needs_six_leading_blanks(): void
    {
        // 2026-02-01 falls on a Sunday — the wrap-around branch of the offset.
        $this->calendar(year: 2026, month: 2)->assertSet('firstDayOffset', 6);
    }

    public function test_selected_date_labels_are_empty_until_a_day_is_picked(): void
    {
        $this->calendar()
            ->assertSet('selectedDateIso', '')
            ->assertSet('selectedDateLabel', '')
            ->call('selectDay', 15)
            ->assertSet('selectedDateIso', '2026-06-15')
            ->assertSet('selectedDateLabel', 'Monday, 15 June 2026');
    }

    public function test_a_past_month_is_not_the_current_month(): void
    {
        $this->calendar(year: 2026, month: 4)->assertSet('isCurrentMonth', false);
    }

    // ── Day selection ─────────────────────────────────────────────────────────

    public function test_selecting_the_same_day_twice_deselects_it(): void
    {
        $this->calendar()
            ->call('selectDay', 12)
            ->assertSet('selectedDay', 12)
            ->call('selectDay', 12)
            ->assertSet('selectedDay', null);
    }

    public function test_changing_the_month_clears_the_selected_day(): void
    {
        $this->calendar()
            ->call('selectDay', 15)
            ->set('month', 5)
            ->assertSet('selectedDay', null);
    }

    // ── Wiring to AttendanceDayPanel ──────────────────────────────────────────

    public function test_the_panel_can_ask_the_calendar_to_deselect_the_day(): void
    {
        $this->calendar()
            ->call('selectDay', 15)
            ->assertSet('selectedDay', 15)
            ->dispatch('attendance-day-closed')
            ->assertSet('selectedDay', null);
    }

    /**
     * Asserts the listeners are registered rather than their effect.
     *
     * refreshEntries() drops a per-request computed cache, and each Livewire
     * test interaction is its own request — so the cache is always cold and an
     * effect-based assertion passes even with the body removed. The wiring is
     * the part a refactor can actually break, so that is what is pinned.
     */
    public function test_it_listens_for_the_panels_announcements(): void
    {
        $listeners = SupportEvents::getComponentListeners($this->calendar()->instance());

        $this->assertSame('refreshEntries', $listeners['attendance-entries-changed'] ?? null);
        $this->assertSame('deselectDay', $listeners['attendance-day-closed'] ?? null);
    }

    public function test_the_grid_reflects_entries_written_since_the_last_render(): void
    {
        $component = $this->calendar();

        $this->assertTrue($component->get('entriesByDay')->isEmpty());

        $entry = $this->entryFor($this->employee, '2026-06-15');

        $ids = $component->call('$refresh')
            ->get('entriesByDay')
            ->flatten()
            ->pluck('id');

        $this->assertTrue($ids->contains($entry->id));
    }

    public function test_the_panel_is_keyed_on_the_selected_date(): void
    {
        $this->calendar()
            ->call('selectDay', 15)
            ->assertSet('selectedDateIso', '2026-06-15')
            ->assertSeeLivewire(AttendanceDayPanel::class);
    }
}
