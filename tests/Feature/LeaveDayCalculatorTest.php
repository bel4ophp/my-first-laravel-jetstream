<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Team;
use App\Models\User;
use App\Services\LeaveDayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveDayCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private LeaveDayCalculator $calculator;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new LeaveDayCalculator;
        $this->team = Team::factory()->create(['user_id' => User::factory()]);
    }

    public function test_counts_weekdays_in_a_full_work_week(): void
    {
        // Mon 2026-06-15 .. Fri 2026-06-19
        $days = $this->calculator->workingDays(
            $this->team,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-19'),
        );

        $this->assertSame(5, $days);
    }

    public function test_excludes_weekends_from_the_range(): void
    {
        // Mon 2026-06-15 .. Sun 2026-06-21 (Sat 20 + Sun 21 excluded)
        $days = $this->calculator->workingDays(
            $this->team,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-21'),
        );

        $this->assertSame(5, $days);
    }

    public function test_excludes_team_holidays_that_fall_within_the_range(): void
    {
        Holiday::factory()->create([
            'team_id' => $this->team->id,
            'date' => '2026-06-17',
        ]);

        $days = $this->calculator->workingDays(
            $this->team,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-19'),
        );

        $this->assertSame(4, $days);
    }

    public function test_ignores_another_teams_holidays(): void
    {
        $otherTeam = Team::factory()->create(['user_id' => User::factory()]);
        Holiday::factory()->create([
            'team_id' => $otherTeam->id,
            'date' => '2026-06-17',
        ]);

        $days = $this->calculator->workingDays(
            $this->team,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-19'),
        );

        $this->assertSame(5, $days);
    }

    public function test_returns_zero_for_a_weekend_only_range(): void
    {
        // Sat 2026-06-20 .. Sun 2026-06-21
        $days = $this->calculator->workingDays(
            $this->team,
            Carbon::parse('2026-06-20'),
            Carbon::parse('2026-06-21'),
        );

        $this->assertSame(0, $days);
    }

    public function test_single_working_day_counts_as_one(): void
    {
        $days = $this->calculator->workingDays(
            $this->team,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-06-15'),
        );

        $this->assertSame(1, $days);
    }
}