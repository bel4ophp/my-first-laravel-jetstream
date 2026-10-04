<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Livewire\LeaveRequestForm;
use App\Livewire\LeaveRequestList;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time to a known Wednesday so the working-week dates below are
        // always in the future regardless of when the suite runs. Roles come
        // from the DB via the base TestCase.
        Carbon::setTestNow('2026-06-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeOwner(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    private function makeManager(Team $team): User
    {
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager, ['role' => 'manager']);

        return $manager;
    }

    private function makeEmployee(Team $team): User
    {
        $employee = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($employee, ['role' => 'employee']);

        return $employee;
    }

    public function test_employee_can_submit_a_leave_request_and_manager_is_notified(): void
    {
        Notification::fake();

        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($employee)
            ->test(LeaveRequestForm::class)
            ->set('type', 'annual')
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-19')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $employee->id,
            'type' => 'annual',
            'calculated_days' => 5,
            'status' => LeaveStatus::Pending->value,
        ]);

        Notification::assertSentTo($manager, LeaveRequestSubmitted::class);
    }

    public function test_manager_submission_notifies_the_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        Livewire::actingAs($manager)
            ->test(LeaveRequestForm::class)
            ->set('type', 'free_day')
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-16')
            ->call('submit')
            ->assertHasNoErrors();

        Notification::assertSentTo($admin, LeaveRequestSubmitted::class);
    }

    public function test_submission_is_blocked_when_pool_is_insufficient(): void
    {
        Notification::fake();

        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'year' => 2026,
            'used_days' => 18,
        ]);

        // Mon..Fri = 5 working days, but only 2 remain.
        Livewire::actingAs($employee)
            ->test(LeaveRequestForm::class)
            ->set('type', 'annual')
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-19')
            ->call('submit')
            ->assertHasErrors('type');

        $this->assertDatabaseCount('leave_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_unpaid_leave_bypasses_the_pool_check(): void
    {
        Notification::fake();

        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        LeaveBalance::factory()->exhausted()->create([
            'user_id' => $employee->id,
            'year' => 2026,
        ]);

        Livewire::actingAs($employee)
            ->test(LeaveRequestForm::class)
            ->set('type', 'unpaid')
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-19')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $employee->id,
            'type' => 'unpaid',
        ]);
    }

    public function test_submission_is_blocked_for_a_weekend_only_range(): void
    {
        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        // Sat 2026-06-20 .. Sun 2026-06-21
        Livewire::actingAs($employee)
            ->test(LeaveRequestForm::class)
            ->set('type', 'annual')
            ->set('startDate', '2026-06-20')
            ->set('endDate', '2026-06-21')
            ->call('submit')
            ->assertHasErrors('startDate');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_sick_type_is_rejected_as_unavailable(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($employee)
            ->test(LeaveRequestForm::class)
            ->set('type', 'sick')
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-19')
            ->call('submit')
            ->assertHasErrors('type');
    }

    /**
     * The form offers exactly what LeaveType::submittable() allows, and the
     * factory draws from the same list. The exclusion used to live only in the
     * form, so fixtures could hold a sick-leave request that could never have
     * been created through the UI.
     */
    public function test_the_form_offers_exactly_the_submittable_types(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        $offered = Livewire::actingAs($employee)
            ->test(LeaveRequestForm::class)
            ->get('availableTypes');

        $this->assertSame(
            collect(LeaveType::submittable())->pluck('value')->all(),
            array_keys($offered)
        );
        $this->assertArrayNotHasKey(LeaveType::Sick->value, $offered);
    }

    public function test_the_factory_never_produces_a_type_the_form_refuses(): void
    {
        $types = LeaveRequest::factory()->count(120)->make()->pluck('type');

        $this->assertFalse($types->contains(LeaveType::Sick));
        $this->assertTrue($types->every(
            fn (LeaveType $type) => in_array($type, LeaveType::submittable(), true)
        ));
    }

    public function test_creator_can_cancel_their_pending_request(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        $request = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'status' => LeaveStatus::Pending,
        ]);

        Livewire::actingAs($employee)
            ->test(LeaveRequestList::class)
            ->call('cancel', $request->id);

        $this->assertSame(LeaveStatus::Cancelled, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->cancelled_at);
    }

    public function test_other_users_cannot_cancel_someone_elses_request(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);
        $other = $this->makeEmployee($owner->currentTeam);

        $request = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'status' => LeaveStatus::Pending,
        ]);

        Livewire::actingAs($other)
            ->test(LeaveRequestList::class)
            ->call('cancel', $request->id)
            ->assertForbidden();

        $this->assertSame(LeaveStatus::Pending, $request->fresh()->status);
    }

    public function test_approved_request_cannot_be_cancelled(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        $request = LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
        ]);

        Livewire::actingAs($employee)
            ->test(LeaveRequestList::class)
            ->call('cancel', $request->id)
            ->assertForbidden();

        $this->assertSame(LeaveStatus::Approved, $request->fresh()->status);
    }

    // ── Users on no team ──────────────────────────────────────────────────────

    /**
     * Holidays — and so the working-day count — are per team. A user on no team
     * used to crash the form as soon as both dates were filled in.
     */
    public function test_a_user_on_no_team_gets_a_message_instead_of_an_error(): void
    {
        $teamless = User::factory()->create();

        Livewire::actingAs($teamless)
            ->test(LeaveRequestForm::class)
            ->set('type', 'annual')
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-19')
            ->assertOk()
            ->call('submit')
            ->assertHasErrors('type');

        $this->assertSame(0, LeaveRequest::where('user_id', $teamless->id)->count());
    }

    // ── Overlapping ranges ────────────────────────────────────────────────────

    /**
     * Reference working week: Mon 2026-06-15 .. Fri 2026-06-19.
     */
    private function submit(User $employee, string $start, string $end, string $type = 'annual')
    {
        return Livewire::actingAs($employee)
            ->test(LeaveRequestForm::class)
            ->set('type', $type)
            ->set('startDate', $start)
            ->set('endDate', $end)
            ->call('submit');
    }

    private function employeeOnATeam(): User
    {
        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);

        return $this->makeEmployee($owner->currentTeam);
    }

    public function test_the_same_range_cannot_be_requested_twice(): void
    {
        $employee = $this->employeeOnATeam();

        $this->submit($employee, '2026-06-15', '2026-06-19')->assertHasNoErrors();
        $this->submit($employee, '2026-06-15', '2026-06-19')->assertHasErrors('startDate');

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_a_partially_overlapping_range_is_rejected(): void
    {
        $employee = $this->employeeOnATeam();

        $this->submit($employee, '2026-06-15', '2026-06-17')->assertHasNoErrors();

        // Starts inside the existing range.
        $this->submit($employee, '2026-06-17', '2026-06-19')->assertHasErrors('startDate');

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_a_range_that_swallows_an_existing_one_is_rejected(): void
    {
        $employee = $this->employeeOnATeam();

        $this->submit($employee, '2026-06-17', '2026-06-17')->assertHasNoErrors();
        $this->submit($employee, '2026-06-15', '2026-06-19')->assertHasErrors('startDate');

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_adjacent_ranges_are_allowed(): void
    {
        $employee = $this->employeeOnATeam();

        $this->submit($employee, '2026-06-15', '2026-06-17')->assertHasNoErrors();

        // Starts the day after the first one ends — no shared dates.
        $this->submit($employee, '2026-06-18', '2026-06-19')->assertHasNoErrors();

        $this->assertDatabaseCount('leave_requests', 2);
    }

    public function test_a_cancelled_request_releases_its_dates(): void
    {
        $employee = $this->employeeOnATeam();

        $this->submit($employee, '2026-06-15', '2026-06-19')->assertHasNoErrors();

        LeaveRequest::first()->update([
            'status' => LeaveStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->submit($employee, '2026-06-15', '2026-06-19')->assertHasNoErrors();

        $this->assertDatabaseCount('leave_requests', 2);
    }

    public function test_a_denied_request_releases_its_dates(): void
    {
        $employee = $this->employeeOnATeam();

        $this->submit($employee, '2026-06-15', '2026-06-19')->assertHasNoErrors();

        LeaveRequest::first()->update(['status' => LeaveStatus::Denied]);

        $this->submit($employee, '2026-06-15', '2026-06-19')->assertHasNoErrors();

        $this->assertDatabaseCount('leave_requests', 2);
    }

    public function test_another_users_request_does_not_block_the_same_dates(): void
    {
        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);
        $first = $this->makeEmployee($owner->currentTeam);
        $second = $this->makeEmployee($owner->currentTeam);

        $this->submit($first, '2026-06-15', '2026-06-19')->assertHasNoErrors();
        $this->submit($second, '2026-06-15', '2026-06-19')->assertHasNoErrors();

        $this->assertDatabaseCount('leave_requests', 2);
    }
}
