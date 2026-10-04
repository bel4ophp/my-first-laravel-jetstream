<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientLeaveDays;
use App\Exceptions\InvalidClockTimes;
use App\Exceptions\LeaveRequestAlreadyDecided;
use App\Exceptions\NoWorkingDaysInRange;
use App\Exceptions\OverlappingLeaveRequest;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Services refuse a business rule by throwing one of these, not a
 * ValidationException keyed to a Livewire field. Each caller decides how to
 * show it: the Livewire components map it to a field or a flash message, and an
 * API request gets a 422 with the message.
 */
class BusinessRuleExceptionTest extends TestCase
{
    /**
     * Factories rather than instances: messages are translated, and data
     * providers run before the application boots.
     *
     * @return array<string, array{0: Closure(): BusinessRuleException, 1: string}>
     */
    public static function exceptions(): array
    {
        return [
            'no working days' => [fn () => new NoWorkingDaysInRange, 'The selected range contains no working days (only weekends/holidays).'],
            'overlap' => [fn () => new OverlappingLeaveRequest, 'You already have a leave request covering part of this range.'],
            'insufficient at submit' => [fn () => InsufficientLeaveDays::toSubmit(3), 'Insufficient leave days. You have 3 day(s) remaining.'],
            'insufficient at approval' => [fn () => InsufficientLeaveDays::toApprove(new User(['name' => 'Ana'])), 'Cannot approve: Ana no longer has enough days in the pool.'],
            'already decided' => [fn () => new LeaveRequestAlreadyDecided(LeaveStatus::Approved), 'This leave request has already been approved.'],
            'clock times' => [fn () => new InvalidClockTimes, 'Clock out must be after clock in.'],
        ];
    }

    #[DataProvider('exceptions')]
    public function test_each_carries_its_message(Closure $make, string $message): void
    {
        $this->assertSame($message, $make()->getMessage());
    }

    public function test_an_api_request_gets_a_422_with_the_message(): void
    {
        $request = Request::create('/api/v1/leave-requests', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);

        $response = (new OverlappingLeaveRequest)->render($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'You already have a leave request covering part of this range.'],
            $response->getData(true),
        );
    }

    public function test_a_web_request_is_left_to_the_caller(): void
    {
        $this->assertNull((new OverlappingLeaveRequest)->render(Request::create('/leave')));
    }

    public function test_they_are_expected_refusals_not_errors_to_log(): void
    {
        $this->assertInstanceOf(ShouldntReport::class, new OverlappingLeaveRequest);
    }
}
