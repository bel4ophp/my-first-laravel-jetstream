<?php

namespace Database\Factories;

use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => now()->year,
            'total_days' => LeaveBalanceService::DEFAULT_POOL_DAYS,
            'used_days' => fake()->numberBetween(0, LeaveBalanceService::DEFAULT_POOL_DAYS),
        ];
    }

    /**
     * No days left in the pool.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_days' => $attributes['total_days'] ?? LeaveBalanceService::DEFAULT_POOL_DAYS,
        ]);
    }

    /**
     * The full pool still available.
     */
    public function fresh(): static
    {
        return $this->state(['used_days' => 0]);
    }
}
