<?php

namespace Tests\Feature;

use App\Enums\LeaveType;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LeaveBalanceService;
    }

    public function test_creates_a_default_balance_when_none_exists(): void
    {
        $user = User::factory()->create();

        $balance = $this->service->currentBalance($user);

        $this->assertSame(now()->year, $balance->year);
        $this->assertSame(LeaveBalanceService::DEFAULT_POOL_DAYS, $balance->total_days);
        $this->assertSame(0, $balance->used_days);
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'year' => now()->year,
        ]);
    }

    public function test_reuses_an_existing_balance(): void
    {
        $user = User::factory()->create();
        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'year' => now()->year,
            'used_days' => 7,
        ]);

        $balance = $this->service->currentBalance($user);

        $this->assertSame(7, $balance->used_days);
        $this->assertSame(1, LeaveBalance::where('user_id', $user->id)->count());
    }

    public function test_pool_types_are_blocked_when_insufficient(): void
    {
        $user = User::factory()->create();
        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'year' => now()->year,
            'used_days' => 18,
        ]);

        $this->assertTrue($this->service->hasSufficientDays($user, LeaveType::Annual, 2));
        $this->assertFalse($this->service->hasSufficientDays($user, LeaveType::Annual, 3));
        $this->assertFalse($this->service->hasSufficientDays($user, LeaveType::FreeDay, 3));
    }

    public function test_non_pool_types_always_pass_the_sufficiency_check(): void
    {
        $user = User::factory()->create();
        LeaveBalance::factory()->exhausted()->create([
            'user_id' => $user->id,
            'year' => now()->year,
        ]);

        $this->assertTrue($this->service->hasSufficientDays($user, LeaveType::Unpaid, 5));
        $this->assertTrue($this->service->hasSufficientDays($user, LeaveType::Sick, 5));
    }

    public function test_deduct_and_restore_adjust_used_days(): void
    {
        $user = User::factory()->create();
        LeaveBalance::factory()->fresh()->create([
            'user_id' => $user->id,
            'year' => now()->year,
        ]);

        $this->service->deduct($user, 5);
        $this->assertSame(15, $this->service->remainingDays($user));

        $this->service->restore($user, 5);
        $this->assertSame(20, $this->service->remainingDays($user));
    }

    public function test_restore_never_drops_used_days_below_zero(): void
    {
        $user = User::factory()->create();
        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'year' => now()->year,
            'used_days' => 2,
        ]);

        $this->service->restore($user, 5);

        $this->assertSame(0, $this->service->currentBalance($user)->used_days);
    }
}