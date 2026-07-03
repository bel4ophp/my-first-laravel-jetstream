<?php

namespace Database\Factories;

use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    public function definition(): array
    {
        $usedDays = fake()->numberBetween(0, 20);

        return [
            'user_id' => User::factory(),
            'year' => now()->year,
            'total_days' => 20,
            'used_days' => $usedDays,
        ];
    }

    public function exhausted(): static
    {
        return $this->state(['used_days' => 20]);
    }

    public function fresh(): static
    {
        return $this->state(['used_days' => 0]);
    }
}