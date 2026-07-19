<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Livewire\LeaveSettings;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Holidays feed the working-day count, so changing them has to re-derive
 * `calculated_days` on requests that already span the affected dates.
 *
 * Reference week: Mon 2026-06-15 .. Fri 2026-06-19 == 5 working days,
 * with Wed 2026-06-17 used as the holiday under test.
 */
class LeaveRecalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $employee;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-10');

        $owner = User::factory()->withPersonalTeam()->create();
        $this->team = $owner->currentTeam;

        $this->manager = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($this->manager, ['role' => 'manager']);

        $this->employee = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($this->employee, ['role' => 'employee']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeRequest(array $attributes = []): LeaveRequest
    {
        return LeaveRequest::factory()->create(array_merge([
            'user_id' => $this->employee->id,
            'type' => LeaveType::Annual,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'calculated_days' => 5,
            'status' => LeaveStatus::Pending,
        ], $attributes));
    }

    private function addHoliday(string $date = '2026-06-17', string $name = 'Test Holiday'): void
    {
        Livewire::actingAs($this->manager)
            ->test(LeaveSettings::class)
            ->set('holidayName', $name)
            ->set('holidayDate', $date)
            ->call('saveHoliday')
            ->assertHasNoErrors();
    }

    public function test_adding_a_holiday_recalculates_a_pending_request(): void
    {
        $request = $this->makeRequest();

        $this->addHoliday();

        $this->assertSame(4, $request->fresh()->calculated_days);
    }

    public function test_adding_a_holiday_recalculates_an_approved_request_and_refunds_the_pool(): void
    {
        $request = $this->makeRequest(['status' => LeaveStatus::Approved]);
        LeaveBalance::factory()->create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'used_days' => 5, // deducted at approval time
        ]);

        $this->addHoliday();

        $this->assertSame(4, $request->fresh()->calculated_days);
        $this->assertSame(4, LeaveBalance::where('user_id', $this->employee->id)->value('used_days'));
    }

    public function test_deleting_a_holiday_recalculates_an_approved_request_and_charges_the_pool(): void
    {
        $holiday = Holiday::factory()->create(['team_id' => $this->team->id, 'date' => '2026-06-17']);
        $request = $this->makeRequest(['status' => LeaveStatus::Approved, 'calculated_days' => 4]);
        LeaveBalance::factory()->create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'used_days' => 4,
        ]);

        Livewire::actingAs($this->manager)
            ->test(LeaveSettings::class)
            ->call('deleteHoliday', $holiday->id);

        $this->assertSame(5, $request->fresh()->calculated_days);
        $this->assertSame(5, LeaveBalance::where('user_id', $this->employee->id)->value('used_days'));
    }

    public function test_unpaid_approved_request_recalculates_without_touching_the_pool(): void
    {
        $request = $this->makeRequest(['status' => LeaveStatus::Approved, 'type' => LeaveType::Unpaid]);
        LeaveBalance::factory()->create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'used_days' => 3,
        ]);

        $this->addHoliday();

        $this->assertSame(4, $request->fresh()->calculated_days);
        $this->assertSame(3, LeaveBalance::where('user_id', $this->employee->id)->value('used_days'));
    }

    public function test_terminal_requests_are_left_alone(): void
    {
        $denied = $this->makeRequest(['status' => LeaveStatus::Denied]);
        $cancelled = $this->makeRequest(['status' => LeaveStatus::Cancelled]);

        $this->addHoliday();

        $this->assertSame(5, $denied->fresh()->calculated_days);
        $this->assertSame(5, $cancelled->fresh()->calculated_days);
    }

    public function test_requests_outside_the_affected_date_are_untouched(): void
    {
        $request = $this->makeRequest(['start_date' => '2026-06-22', 'end_date' => '2026-06-26']);

        $this->addHoliday();

        $this->assertSame(5, $request->fresh()->calculated_days);
    }

    public function test_another_teams_requests_are_untouched(): void
    {
        $otherOwner = User::factory()->withPersonalTeam()->create();
        $otherEmployee = User::factory()->create(['current_team_id' => $otherOwner->currentTeam->id]);
        $otherOwner->currentTeam->users()->attach($otherEmployee, ['role' => 'employee']);

        $foreign = LeaveRequest::factory()->create([
            'user_id' => $otherEmployee->id,
            'type' => LeaveType::Annual,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'calculated_days' => 5,
            'status' => LeaveStatus::Pending,
        ]);

        $this->addHoliday();

        $this->assertSame(5, $foreign->fresh()->calculated_days);
    }

    public function test_request_starting_exactly_on_the_holiday_date_is_recalculated(): void
    {
        // Boundary case: the overlap check is `start_date <= date <= end_date`.
        $request = $this->makeRequest([
            'start_date' => '2026-06-17',
            'end_date' => '2026-06-19',
            'calculated_days' => 3,
        ]);

        $this->addHoliday();

        $this->assertSame(2, $request->fresh()->calculated_days);
    }

    public function test_request_ending_exactly_on_the_holiday_date_is_recalculated(): void
    {
        $request = $this->makeRequest([
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-17',
            'calculated_days' => 3,
        ]);

        $this->addHoliday();

        $this->assertSame(2, $request->fresh()->calculated_days);
    }

    public function test_moving_a_holiday_recalculates_both_the_old_and_new_ranges(): void
    {
        $holiday = Holiday::factory()->create(['team_id' => $this->team->id, 'date' => '2026-06-17']);

        $weekOne = $this->makeRequest(['calculated_days' => 4]); // 06-15..19, holiday inside
        $weekTwo = $this->makeRequest([
            'start_date' => '2026-06-22',
            'end_date' => '2026-06-26',
            'calculated_days' => 5,
        ]);

        // Move the holiday from week one into week two.
        Livewire::actingAs($this->manager)
            ->test(LeaveSettings::class)
            ->call('editHoliday', $holiday->id)
            ->set('holidayDate', '2026-06-24')
            ->call('saveHoliday')
            ->assertHasNoErrors();

        $this->assertSame(5, $weekOne->fresh()->calculated_days);
        $this->assertSame(4, $weekTwo->fresh()->calculated_days);
    }
}