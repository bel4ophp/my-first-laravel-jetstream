<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        $startDate = Carbon::parse(fake()->dateTimeBetween('now', '+2 months'));
        $endDate = $startDate->copy()->addDays(fake()->numberBetween(0, 6));

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(LeaveType::cases()),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'calculated_days' => fake()->numberBetween(1, 5),
            'status' => LeaveStatus::Pending,
            'approved_by' => null,
            'notes' => fake()->optional()->sentence(),
            'cancelled_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => LeaveStatus::Approved,
            'approved_by' => User::factory(),
        ]);
    }

    public function denied(): static
    {
        return $this->state([
            'status' => LeaveStatus::Denied,
            'approved_by' => User::factory(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => LeaveStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}