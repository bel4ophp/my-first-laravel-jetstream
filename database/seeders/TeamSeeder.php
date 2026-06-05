<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /** @var array<int, array{name: string, slug: string}> */
    private const TEAMS = [
        ['name' => 'Alpha Team',   'slug' => 'alpha'],
        ['name' => 'Beta Team',    'slug' => 'beta'],
        ['name' => 'Gamma Team',   'slug' => 'gamma'],
        ['name' => 'Delta Team',   'slug' => 'delta'],
        ['name' => 'Epsilon Team', 'slug' => 'epsilon'],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@bel4o.dev')->firstOrFail();
        $firstTeam = null;

        foreach (self::TEAMS as $definition) {
            $team = Team::firstOrCreate(
                ['name' => $definition['name'], 'user_id' => $admin->id],
                ['personal_team' => false]
            );

            $manager = $this->createMember(
                name: ucfirst($definition['slug']) . ' Manager',
                email: "manager.{$definition['slug']}@bel4o.dev",
                team: $team,
                role: 'manager',
            );

            $manager->current_team_id || $manager->switchTeam($team);

            for ($i = 1; $i <= 3; $i++) {
                $employee = $this->createMember(
                    name: ucfirst($definition['slug']) . " Employee {$i}",
                    email: "employee{$i}.{$definition['slug']}@bel4o.dev",
                    team: $team,
                    role: 'employee',
                );

                $employee->current_team_id || $employee->switchTeam($team);

                $this->seedMonthlyEntries($employee);
            }

            $firstTeam ??= $team;
        }

        // Switch admin to first work team so currentTeam is meaningful on login
        $admin->switchTeam($firstTeam);
    }

    private function createMember(string $name, string $email, Team $team, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        if (! $team->users()->where('users.id', $user->id)->exists()) {
            $team->users()->attach($user, ['role' => $role]);
        }

        return $user;
    }

    private function seedMonthlyEntries(User $user): void
    {
        $start = now()->startOfMonth()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        for ($day = $start->copy(); $day->lte($yesterday); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            TimeEntry::firstOrCreate(
                ['user_id' => $user->id, 'work_day' => $day->format('Y-m-d')],
                [
                    'clock_in' => $day->copy()->setTime(8, 0),
                    'clock_out' => $day->copy()->setTime(16, 0),
                    'worked_minutes' => 480,
                ]
            );
        }
    }
}