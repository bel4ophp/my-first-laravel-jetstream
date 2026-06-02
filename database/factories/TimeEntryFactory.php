<?php

namespace Database\Factories;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clockIn  = Carbon::instance($this->faker->dateTimeBetween('-1 month', 'now'))->setTime(8, 0);
        $clockOut = $clockIn->copy()->addHours(8);

        return [
            'user_id'        => User::factory(),
            'work_day'       => $clockIn->format('Y-m-d'),
            'clock_in'       => $clockIn,
            'clock_out'      => $clockOut,
            'worked_minutes' => 480,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['clock_out' => null, 'worked_minutes' => null]);
    }

    public function forDay(string $date): static
    {
        return $this->state(function () use ($date) {
            $clockIn = Carbon::parse($date)->setTime(8, 0);

            return [
                'work_day'       => $date,
                'clock_in'       => $clockIn,
                'clock_out'      => $clockIn->copy()->addHours(8),
                'worked_minutes' => 480,
            ];
        });
    }
}
