<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Livewire\AttendanceDayPanel;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The day detail panel: entry list, inline bulk editor and add-entry form.
 *
 * These assertions were written against AttendanceCalendar before the panel was
 * split out, and moved here unchanged — the behaviour is meant to be identical,
 * only its owner moved.
 */
class AttendanceDayPanelTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-06-15';

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

    private function panel(?User $as = null, string $date = self::DATE)
    {
        return Livewire::actingAs($as ?? $this->manager)
            ->test(AttendanceDayPanel::class, ['date' => $date]);
    }

    private function entryFor(User $user, string $workDay): TimeEntry
    {
        return TimeEntry::factory()->forDay($workDay)->create(['user_id' => $user->id]);
    }

    /**
     * An employee on a team this test's manager has nothing to do with.
     */
    private function foreignEmployee(): User
    {
        $foreignTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

        $foreigner = User::factory()->create(['current_team_id' => $foreignTeam->id]);
        $foreignTeam->users()->attach($foreigner, ['role' => TeamRole::Employee->value]);

        return $foreigner;
    }

    // ── Entry list ────────────────────────────────────────────────────────────

    public function test_it_lists_only_the_given_days_entries(): void
    {
        $onDay = $this->entryFor($this->employee, self::DATE);
        $otherDay = $this->entryFor($this->employee, '2026-06-16');

        $ids = $this->panel()->get('entries')->pluck('id');

        $this->assertTrue($ids->contains($onDay->id));
        $this->assertFalse($ids->contains($otherDay->id));
    }

    public function test_it_does_not_list_another_teams_entries(): void
    {
        $stranger = User::factory()->withPersonalTeam()->create();
        $foreign = $this->entryFor($stranger, self::DATE);

        $ids = $this->panel()->get('entries')->pluck('id');

        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_closing_asks_the_calendar_to_deselect_the_day(): void
    {
        $this->panel()
            ->call('close')
            ->assertDispatched('attendance-day-closed');
    }

    // ── Inline editing ────────────────────────────────────────────────────────

    public function test_entering_edit_mode_seeds_the_form_from_existing_entries(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel()
            ->call('toggleEditingEntries')
            ->assertSet('editingEntries', true)
            ->assertSet("entryEdits.{$entry->id}.clock_in", $entry->clockInFormatted())
            ->assertSet("entryEdits.{$entry->id}.clock_out", $entry->clockOutFormatted());
    }

    public function test_leaving_edit_mode_clears_the_form(): void
    {
        $this->entryFor($this->employee, self::DATE);

        $this->panel()
            ->call('toggleEditingEntries')
            ->call('toggleEditingEntries')
            ->assertSet('editingEntries', false)
            ->assertSet('entryEdits', []);
    }

    public function test_saving_an_edit_persists_the_new_times_and_recomputes_minutes(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel()
            ->call('toggleEditingEntries')
            ->set("entryEdits.{$entry->id}.clock_in", '09:30')
            ->set("entryEdits.{$entry->id}.clock_out", '17:00')
            ->call('saveEntryEdits', $entry->id)
            ->assertHasNoErrors()
            ->assertDispatched('attendance-entries-changed');

        $entry->refresh();

        $this->assertSame('09:30', $entry->clockInFormatted());
        $this->assertSame('17:00', $entry->clockOutFormatted());
        $this->assertSame(450, $entry->worked_minutes);
    }

    public function test_saving_an_edit_rejects_a_clock_out_before_the_clock_in(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);
        $originalClockIn = $entry->clockInFormatted();

        $this->panel()
            ->call('toggleEditingEntries')
            ->set("entryEdits.{$entry->id}.clock_in", '17:00')
            ->set("entryEdits.{$entry->id}.clock_out", '09:00')
            ->call('saveEntryEdits', $entry->id)
            ->assertHasErrors("entryEdits.{$entry->id}.clock_out");

        $this->assertSame($originalClockIn, $entry->fresh()->clockInFormatted());
    }

    public function test_saving_an_edit_rejects_a_malformed_time(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel()
            ->call('toggleEditingEntries')
            ->set("entryEdits.{$entry->id}.clock_in", 'half past nine')
            ->call('saveEntryEdits', $entry->id)
            ->assertHasErrors("entryEdits.{$entry->id}.clock_in");
    }

    public function test_an_entry_can_be_cleared_back_to_running_by_blanking_the_clock_out(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel()
            ->call('toggleEditingEntries')
            ->set("entryEdits.{$entry->id}.clock_out", null)
            ->call('saveEntryEdits', $entry->id)
            ->assertHasNoErrors();

        $entry->refresh();

        $this->assertNull($entry->clock_out);
        $this->assertNull($entry->worked_minutes);
    }

    public function test_an_employee_cannot_save_an_edit(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel(as: $this->employee)
            ->set("entryEdits.{$entry->id}.clock_in", '09:30')
            ->call('saveEntryEdits', $entry->id)
            ->assertForbidden();
    }

    public function test_a_manager_cannot_save_an_edit_to_another_teams_entry(): void
    {
        $entry = $this->entryFor($this->foreignEmployee(), self::DATE);
        $originalClockIn = $entry->clockInFormatted();

        $this->panel()
            ->set("entryEdits.{$entry->id}.clock_in", '03:00')
            ->set("entryEdits.{$entry->id}.clock_out", '04:00')
            ->call('saveEntryEdits', $entry->id)
            ->assertForbidden();

        $this->assertSame($originalClockIn, $entry->fresh()->clockInFormatted());
    }

    // ── Deleting ──────────────────────────────────────────────────────────────

    public function test_a_manager_can_delete_an_entry(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel()
            ->call('deleteEntry', $entry->id)
            ->assertDispatched('attendance-entries-changed');

        $this->assertModelMissing($entry);
    }

    public function test_an_employee_cannot_delete_an_entry(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel(as: $this->employee)
            ->call('deleteEntry', $entry->id)
            ->assertForbidden();

        $this->assertModelExists($entry);
    }

    public function test_a_team_owner_can_delete_an_entry_on_their_own_team(): void
    {
        $entry = $this->entryFor($this->employee, self::DATE);

        $this->panel(as: $this->owner)
            ->call('deleteEntry', $entry->id)
            ->assertDispatched('attendance-entries-changed');

        $this->assertModelMissing($entry);
    }

    public function test_a_manager_cannot_delete_another_teams_entry(): void
    {
        $entry = $this->entryFor($this->foreignEmployee(), self::DATE);

        $this->panel()
            ->call('deleteEntry', $entry->id)
            ->assertForbidden();

        $this->assertModelExists($entry);
    }

    // ── Creating ──────────────────────────────────────────────────────────────

    public function test_opening_the_create_form_starts_it_blank(): void
    {
        $this->panel()
            ->set('createForm.clock_in', '09:00')
            ->call('startCreatingEntry')
            ->assertSet('creatingEntry', true)
            ->assertSet('createForm.user_id', null)
            ->assertSet('createForm.clock_in', '')
            ->assertSet('createForm.clock_out', null);
    }

    public function test_cancelling_the_create_form_closes_and_blanks_it(): void
    {
        $this->panel()
            ->call('startCreatingEntry')
            ->set('createForm.clock_in', '09:00')
            ->call('cancelCreatingEntry')
            ->assertSet('creatingEntry', false)
            ->assertSet('createForm.clock_in', '');
    }

    public function test_a_manager_can_create_an_entry_on_the_panels_day(): void
    {
        $this->panel()
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $this->employee->id)
            ->set('createForm.clock_in', '08:00')
            ->set('createForm.clock_out', '16:00')
            ->call('saveNewEntry')
            ->assertHasNoErrors()
            ->assertDispatched('attendance-entries-changed');

        $entry = TimeEntry::where('user_id', $this->employee->id)->latest('id')->first();

        $this->assertSame(self::DATE, $entry->work_day->toDateString());
        $this->assertSame('08:00', $entry->clockInFormatted());
        $this->assertSame(480, $entry->worked_minutes);
    }

    public function test_creating_an_entry_closes_the_form_outside_edit_mode(): void
    {
        $this->panel()
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $this->employee->id)
            ->set('createForm.clock_in', '08:00')
            ->call('saveNewEntry')
            ->assertSet('creatingEntry', false);
    }

    public function test_creating_an_entry_keeps_the_form_open_in_edit_mode(): void
    {
        $this->entryFor($this->employee, self::DATE);

        $this->panel()
            ->call('toggleEditingEntries')
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $this->employee->id)
            ->set('createForm.clock_in', '08:00')
            ->call('saveNewEntry')
            ->assertHasNoErrors()
            ->assertSet('creatingEntry', true)
            ->assertSet('createForm.clock_in', '');
    }

    public function test_the_new_entry_becomes_editable_straight_away_in_edit_mode(): void
    {
        $this->entryFor($this->employee, self::DATE);

        $component = $this->panel()
            ->call('toggleEditingEntries')
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $this->owner->id)
            ->set('createForm.clock_in', '08:00')
            ->call('saveNewEntry');

        $created = TimeEntry::where('user_id', $this->owner->id)->latest('id')->first();

        $component->assertSet("entryEdits.{$created->id}.clock_in", '08:00');
    }

    public function test_creating_an_entry_rejects_a_user_outside_the_selectable_list(): void
    {
        $stranger = User::factory()->withPersonalTeam()->create();

        $this->panel()
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $stranger->id)
            ->set('createForm.clock_in', '08:00')
            ->call('saveNewEntry')
            ->assertHasErrors('createForm.user_id');

        $this->assertSame(0, TimeEntry::where('user_id', $stranger->id)->count());
    }

    public function test_a_team_owner_cannot_create_an_entry_for_another_teams_user(): void
    {
        $foreigner = $this->foreignEmployee();

        $this->panel(as: $this->owner)
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $foreigner->id)
            ->set('createForm.clock_in', '08:00')
            ->call('saveNewEntry')
            ->assertHasErrors('createForm.user_id');

        $this->assertSame(0, TimeEntry::where('user_id', $foreigner->id)->count());
    }

    public function test_a_team_owner_can_create_an_entry_for_their_own_teams_user(): void
    {
        $this->panel(as: $this->owner)
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $this->employee->id)
            ->set('createForm.clock_in', '08:00')
            ->call('saveNewEntry')
            ->assertHasNoErrors();

        $this->assertSame(1, TimeEntry::where('user_id', $this->employee->id)->count());
    }

    public function test_creating_an_entry_rejects_a_clock_out_before_the_clock_in(): void
    {
        $this->panel()
            ->call('startCreatingEntry')
            ->set('createForm.user_id', $this->employee->id)
            ->set('createForm.clock_in', '17:00')
            ->set('createForm.clock_out', '09:00')
            ->call('saveNewEntry')
            ->assertHasErrors('createForm.clock_out');

        $this->assertSame(0, TimeEntry::where('user_id', $this->employee->id)->count());
    }

    public function test_an_employee_cannot_create_an_entry(): void
    {
        $this->panel(as: $this->employee)
            ->set('createForm.user_id', $this->employee->id)
            ->set('createForm.clock_in', '08:00')
            ->call('saveNewEntry')
            ->assertForbidden();
    }

    // ── Permission flags the template renders against ─────────────────────────

    public function test_a_manager_gets_the_management_flags(): void
    {
        $this->panel()
            ->assertSet('canViewTimeEntries', true)
            ->assertSet('canCreateTimeEntries', true)
            ->assertSet('canUpdateTimeEntries', true)
            ->assertSet('canDeleteTimeEntries', true)
            ->assertSet('canExportAttendance', true);
    }

    public function test_an_employee_gets_none_of_the_management_flags(): void
    {
        $this->panel(as: $this->employee)
            ->assertSet('canCreateTimeEntries', false)
            ->assertSet('canUpdateTimeEntries', false)
            ->assertSet('canDeleteTimeEntries', false)
            ->assertSet('canExportAttendance', false);
    }

    public function test_the_header_label_reads_from_the_date_prop(): void
    {
        $this->panel()->assertSet('dateLabel', 'Monday, 15 June 2026');
    }
}
