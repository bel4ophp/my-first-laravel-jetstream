<?php

namespace Tests\Feature;

use App\Enums\ClockAction;
use App\Enums\LeaveType;
use App\Livewire\Notifications\Dropdown;
use App\Models\LeaveRequest;
use App\Models\TimeEntry;
use App\Models\User;
use App\Notifications\LeaveRequestCancelled;
use App\Notifications\LeaveRequestStatusChanged;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\UserClockedInNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The bell dropdown describes each kind of notification in its own words.
 *
 * It used to render every row as "<employee> Clocked <action>", so leave
 * notifications read "Clocked" and clock-ins read "Clocked clock_in".
 * Notifications are sent through the real classes here, so the stored data
 * has exactly the shape production writes.
 */
class NotificationDropdownTest extends TestCase
{
    use RefreshDatabase;

    private User $recipient;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipient = User::factory()->withPersonalTeam()->create();
        $this->employee = User::factory()->create(['name' => 'Ana Petrović']);
    }

    private function leaveRequest(array $attributes = []): LeaveRequest
    {
        return LeaveRequest::factory()->create([
            'user_id' => $this->employee->id,
            'type' => LeaveType::Annual,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'calculated_days' => 5,
            ...$attributes,
        ]);
    }

    private function dropdown()
    {
        return Livewire::actingAs($this->recipient)->test(Dropdown::class);
    }

    public function test_a_clock_in_reads_as_a_clock_in(): void
    {
        $entry = TimeEntry::factory()->active()->create(['user_id' => $this->employee->id]);
        $this->recipient->notify(new UserClockedInNotification($this->employee, $entry, ClockAction::ClockIn));

        $this->dropdown()
            ->assertSee('Ana Petrović')
            ->assertSee('Clocked in')
            ->assertDontSee('clock_in');
    }

    public function test_a_submitted_leave_request_names_the_employee_and_the_leave(): void
    {
        $this->recipient->notify(new LeaveRequestSubmitted($this->leaveRequest()));

        $this->dropdown()
            ->assertSee('Ana Petrović')
            ->assertSee('Requested Annual Leave')
            ->assertSee('Jun 15, 2026')
            ->assertDontSee('Clocked');
    }

    public function test_a_decision_tells_the_employee_the_outcome(): void
    {
        $this->recipient->notify(new LeaveRequestStatusChanged($this->leaveRequest(['status' => 'approved'])));

        $this->dropdown()
            ->assertSee('Your leave request')
            ->assertSee('Annual Leave')
            ->assertSee('approved')
            ->assertDontSee('System')
            ->assertDontSee('Clocked');
    }

    public function test_a_cancellation_says_so(): void
    {
        $this->recipient->notify(new LeaveRequestCancelled($this->leaveRequest(['status' => 'cancelled'])));

        $this->dropdown()
            ->assertSee('Ana Petrović')
            ->assertSee('Cancelled Annual Leave')
            ->assertDontSee('Clocked');
    }

    public function test_an_unknown_notification_still_renders(): void
    {
        $this->recipient->notifications()->create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'type' => 'legacy',
            'data' => ['type' => 'something_old'],
        ]);

        // Rows from older notification shapes must not break the dropdown.
        $this->dropdown()->assertOk();
    }
}
