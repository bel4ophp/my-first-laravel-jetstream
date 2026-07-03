<?php

namespace Database\Factories;

use App\Models\Holiday;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(3, true),
            'date' => fake()->dateTimeBetween('this year', '+1 year')->format('Y-m-d'),
        ];
    }
}