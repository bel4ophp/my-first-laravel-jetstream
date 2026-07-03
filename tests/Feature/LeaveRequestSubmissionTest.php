<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
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
}