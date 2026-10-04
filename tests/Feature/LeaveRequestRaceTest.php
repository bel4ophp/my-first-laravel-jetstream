<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Exceptions\InsufficientLeaveDays;
use App\Exceptions\LeaveRequestAlreadyDecided;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeaveRequestStatusChanged;
use App\Services\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Two decisions on the same request racing each other.
 *
 * Each test holds a stale copy of a request — still "pending" in memory after
 * another request has already decided it — which is exactly what the slower of
 * two simultaneous requests sees. The service must re-read the request under a
 * lock and refuse to act on a decision that has already been made.
 *
 * The tests run one request after another, so they prove the re-check rather
 * than the lock; the lock is what makes truly simultaneous requests wait.
 */
class LeaveRequestRaceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveRequestService $service;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-10');
        Notification::fake();

        $this->service = app(LeaveRequestService::class);

        $team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $this->manager = $this->memberOf($team, 'manager');
        $this->employee = $this->memberOf($team, 'employee');

        LeaveBalance::factory()->fresh()->create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'total_days' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function memberOf(Team $team, string $role): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function pendingRequest(int $days = 3, string $start = '2026-06-15', string $end = '2026-06-17'): LeaveRequest
    {
        return LeaveRequest::factory()->create([
            'user_id' => $this->employee->id,
            'type' => LeaveType::Annual,
            'start_date' => $start,
            'end_date' => $end,
            'calculated_days' => $days,
            'status' => LeaveStatus::Pending,
        ]);
    }

    /**
     * A second, independently loaded copy — still pending in memory once the
     * first copy has been decided.
     */
    private function staleCopyOf(LeaveRequest $request): LeaveRequest
    {
        return LeaveRequest::findOrFail($request->id);
    }

    private function usedDays(): int
    {
        return LeaveBalance::where('user_id', $this->employee->id)->where('year', 2026)->value('used_days');
    }

    public function test_approving_the_same_request_twice_deducts_its_days_once(): void
    {
        $request = $this->pendingRequest(days: 3);
        $stale = $this->staleCopyOf($request);

        $this->service->approve($request, $this->manager);

        try {
            $this->service->approve($stale, $this->manager);
            $this->fail('The same request was approved twice.');
        } catch (LeaveRequestAlreadyDecided) {
            // Expected: the request was already decided.
        }

        $this->assertSame(3, $this->usedDays());
        Notification::assertSentToTimes($this->employee, LeaveRequestStatusChanged::class, 1);
    }

    public function test_a_request_approved_elsewhere_cannot_then_be_denied(): void
    {
        $request = $this->pendingRequest(days: 3);
        $stale = $this->staleCopyOf($request);

        $this->service->approve($request, $this->manager);

        try {
            $this->service->deny($stale, $this->manager);
            $this->fail('An approved request was then denied.');
        } catch (LeaveRequestAlreadyDecided) {
            // Expected.
        }

        $this->assertSame(LeaveStatus::Approved, $request->fresh()->status);
        $this->assertSame(3, $this->usedDays());
    }

    public function test_a_request_approved_elsewhere_cannot_then_be_cancelled(): void
    {
        $request = $this->pendingRequest(days: 3);
        $stale = $this->staleCopyOf($request);

        $this->service->approve($request, $this->manager);

        try {
            $this->service->cancel($stale);
            $this->fail('An approved request was then cancelled.');
        } catch (LeaveRequestAlreadyDecided) {
            // Expected.
        }

        $this->assertSame(LeaveStatus::Approved, $request->fresh()->status);
        $this->assertSame(3, $this->usedDays());
    }

    public function test_a_request_cancelled_elsewhere_cannot_then_be_approved(): void
    {
        $request = $this->pendingRequest(days: 3);
        $stale = $this->staleCopyOf($request);

        $this->service->cancel($request);

        try {
            $this->service->approve($stale, $this->manager);
            $this->fail('A cancelled request was then approved.');
        } catch (LeaveRequestAlreadyDecided) {
            // Expected.
        }

        $this->assertSame(LeaveStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(0, $this->usedDays());
    }

    public function test_two_requests_that_together_exceed_the_pool_cannot_both_be_approved(): void
    {
        LeaveBalance::where('user_id', $this->employee->id)->update(['used_days' => 15]);

        $first = $this->pendingRequest(days: 3, start: '2026-06-15', end: '2026-06-17');
        $second = $this->pendingRequest(days: 3, start: '2026-06-22', end: '2026-06-24');

        $this->service->approve($first, $this->manager);

        try {
            $this->service->approve($second, $this->manager);
            $this->fail('The pool was overdrawn.');
        } catch (InsufficientLeaveDays) {
            // Expected: only 2 days were left.
        }

        $this->assertSame(18, $this->usedDays());
        $this->assertSame(LeaveStatus::Pending, $second->fresh()->status);
    }

    public function test_cancelling_sets_the_status_and_timestamp(): void
    {
        $request = $this->pendingRequest();

        $this->service->cancel($request);

        $this->assertSame(LeaveStatus::Cancelled, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->cancelled_at);
    }
}
