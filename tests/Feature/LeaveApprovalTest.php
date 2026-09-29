<?php

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Livewire\LeaveApprovals;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeaveRequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles come from the DB via the base TestCase.
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

    private function pendingRequest(User $user, LeaveType $type, int $days): LeaveRequest
    {
        return LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'type' => $type,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'calculated_days' => $days,
            'status' => LeaveStatus::Pending,
        ]);
    }

    public function test_manager_approves_employee_request_deducts_pool_and_notifies(): void
    {
        Notification::fake();

        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        LeaveBalance::factory()->fresh()->create(['user_id' => $employee->id, 'year' => 2026]);
        $request = $this->pendingRequest($employee, LeaveType::Annual, 3);

        Livewire::actingAs($manager)
            ->test(LeaveApprovals::class)
            ->call('approve', $request->id);

        $this->assertSame(LeaveStatus::Approved, $request->fresh()->status);
        $this->assertSame($manager->id, $request->fresh()->approved_by);
        $this->assertSame(3, LeaveBalance::where('user_id', $employee->id)->where('year', 2026)->first()->used_days);
        Notification::assertSentTo($employee, LeaveRequestStatusChanged::class);
    }

    public function test_denied_request_does_not_deduct_pool(): void
    {
        Notification::fake();

        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        LeaveBalance::factory()->fresh()->create(['user_id' => $employee->id, 'year' => 2026]);
        $request = $this->pendingRequest($employee, LeaveType::Annual, 3);

        Livewire::actingAs($manager)
            ->test(LeaveApprovals::class)
            ->call('deny', $request->id);

        $this->assertSame(LeaveStatus::Denied, $request->fresh()->status);
        $this->assertSame(0, LeaveBalance::where('user_id', $employee->id)->where('year', 2026)->first()->used_days);
        Notification::assertSentTo($employee, LeaveRequestStatusChanged::class);
    }

    public function test_unpaid_leave_approval_does_not_touch_the_pool(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        LeaveBalance::factory()->fresh()->create(['user_id' => $employee->id, 'year' => 2026]);
        $request = $this->pendingRequest($employee, LeaveType::Unpaid, 3);

        Livewire::actingAs($manager)
            ->test(LeaveApprovals::class)
            ->call('approve', $request->id);

        $this->assertSame(LeaveStatus::Approved, $request->fresh()->status);
        $this->assertSame(0, LeaveBalance::where('user_id', $employee->id)->where('year', 2026)->first()->used_days);
    }

    public function test_admin_approves_manager_request_and_notifies(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        LeaveBalance::factory()->fresh()->create(['user_id' => $manager->id, 'year' => 2026]);
        $request = $this->pendingRequest($manager, LeaveType::Annual, 2);

        Livewire::actingAs($admin)
            ->test(LeaveApprovals::class)
            ->call('approve', $request->id);

        $this->assertSame(LeaveStatus::Approved, $request->fresh()->status);
        $this->assertSame(2, LeaveBalance::where('user_id', $manager->id)->where('year', 2026)->first()->used_days);
        Notification::assertSentTo($manager, LeaveRequestStatusChanged::class);
    }

    public function test_approval_is_blocked_when_pool_no_longer_covers_request(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        // Only 2 days remain, but the pending request needs 5.
        LeaveBalance::factory()->create(['user_id' => $employee->id, 'year' => 2026, 'used_days' => 18]);
        $request = $this->pendingRequest($employee, LeaveType::Annual, 5);

        // Approval is rejected via a flashed error (not an exception); the
        // request stays pending and no days are deducted.
        Livewire::actingAs($manager)
            ->test(LeaveApprovals::class)
            ->call('approve', $request->id)
            ->assertHasNoErrors();

        $this->assertSame(LeaveStatus::Pending, $request->fresh()->status);
        $this->assertSame(18, LeaveBalance::where('user_id', $employee->id)->where('year', 2026)->first()->used_days);
    }

    public function test_manager_cannot_approve_another_teams_request(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $otherOwner = $this->makeOwner();
        $otherEmployee = $this->makeEmployee($otherOwner->currentTeam);
        $request = $this->pendingRequest($otherEmployee, LeaveType::Annual, 2);

        Livewire::actingAs($manager)
            ->test(LeaveApprovals::class)
            ->call('approve', $request->id)
            ->assertForbidden();

        $this->assertSame(LeaveStatus::Pending, $request->fresh()->status);
    }

    public function test_manager_cannot_approve_their_own_request(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        $request = $this->pendingRequest($manager, LeaveType::Annual, 2);

        Livewire::actingAs($manager)
            ->test(LeaveApprovals::class)
            ->call('approve', $request->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_approve_requests(): void
    {
        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);
        $other = $this->makeEmployee($owner->currentTeam);

        $request = $this->pendingRequest($other, LeaveType::Annual, 2);

        Livewire::actingAs($employee)
            ->test(LeaveApprovals::class)
            ->call('approve', $request->id)
            ->assertForbidden();
    }

    public function test_manager_only_sees_their_teams_pending_employee_requests(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        $otherOwner = $this->makeOwner();
        $otherEmployee = $this->makeEmployee($otherOwner->currentTeam);

        $own = $this->pendingRequest($employee, LeaveType::Annual, 2);
        $foreign = $this->pendingRequest($otherEmployee, LeaveType::Annual, 2);

        $ids = Livewire::actingAs($manager)
            ->test(LeaveApprovals::class)
            ->get('pendingRequests')
            ->pluck('id');

        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_admin_sees_pending_manager_requests_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        $managerRequest = $this->pendingRequest($manager, LeaveType::Annual, 2);
        $employeeRequest = $this->pendingRequest($employee, LeaveType::Annual, 2);

        $ids = Livewire::actingAs($admin)
            ->test(LeaveApprovals::class)
            ->get('pendingRequests')
            ->pluck('id');

        $this->assertTrue($ids->contains($managerRequest->id));
        $this->assertFalse($ids->contains($employeeRequest->id));
    }

    /**
     * Guards the mismatch this replaced: the queue used to list every pending
     * request on a team for anyone holding the approve permission, while the
     * policy only let the resolved approver act — so a second manager was shown
     * buttons that answered 403.
     */
    public function test_queue_only_lists_requests_the_viewer_can_actually_approve(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        // Data that predates the single-manager rule: a second manager on the team.
        $secondManager = User::factory()->create(['current_team_id' => $owner->currentTeam->id]);
        $owner->currentTeam->users()->attach($secondManager, ['role' => 'manager']);

        $request = $this->pendingRequest($employee, LeaveType::Annual, 2);

        $ids = Livewire::actingAs($secondManager)
            ->test(LeaveApprovals::class)
            ->get('pendingRequests')
            ->pluck('id');

        $this->assertFalse($ids->contains($request->id));

        // And the manager who *is* the approver still sees it.
        $this->assertTrue(
            Livewire::actingAs($manager)
                ->test(LeaveApprovals::class)
                ->get('pendingRequests')
                ->pluck('id')
                ->contains($request->id)
        );
    }

    // ── One manager per team ──────────────────────────────────────────────────

    public function test_a_second_manager_cannot_be_added_to_a_team(): void
    {
        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);

        $candidate = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(AddTeamMember::class)->add($owner, $owner->currentTeam, $candidate->email, 'manager');
    }

    public function test_a_member_cannot_be_promoted_to_a_second_manager(): void
    {
        $owner = $this->makeOwner();
        $this->makeManager($owner->currentTeam);
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($owner)
            ->test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->set('managingRoleFor', $employee)
            ->set('currentRole', 'manager')
            ->call('updateRole')
            ->assertHasErrors('role');

        $this->assertTrue($employee->fresh()->hasTeamRole($owner->currentTeam->fresh(), 'employee'));
    }

    public function test_the_existing_manager_can_be_resaved_as_manager(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager($owner->currentTeam);

        Livewire::actingAs($owner)
            ->test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->set('managingRoleFor', $manager)
            ->set('currentRole', 'manager')
            ->call('updateRole')
            ->assertHasNoErrors();

        $this->assertTrue($manager->fresh()->hasTeamRole($owner->currentTeam->fresh(), 'manager'));
    }

    public function test_a_team_without_a_manager_still_accepts_one(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployee($owner->currentTeam);

        Livewire::actingAs($owner)
            ->test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->set('managingRoleFor', $employee)
            ->set('currentRole', 'manager')
            ->call('updateRole')
            ->assertHasNoErrors();

        $this->assertTrue($owner->currentTeam->fresh()->manager()->is($employee));
    }
}