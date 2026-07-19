<?php

namespace Tests\Feature;

use App\Events\UserClockedInEvent;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Notifications\UserClockedInNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserClockedInNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeamWithManager(): array
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager, ['role' => 'manager']);

        return [$team, $manager];
    }

    private function clockIn(User $user, Team $team): TimeEntry
    {
        $user->forceFill(['current_team_id' => $team->id])->save();

        $timeEntry = TimeEntry::factory()->active()->create(['user_id' => $user->id]);

        event(new UserClockedInEvent($user->fresh(), $timeEntry));

        return $timeEntry;
    }

    public function test_manager_and_admin_are_notified_when_an_employee_clocks_in(): void
    {
        Notification::fake();

        [$team, $manager] = $this->makeTeamWithManager();
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create();
        $team->users()->attach($employee, ['role' => 'employee']);

        $this->clockIn($employee, $team);

        Notification::assertSentTo($manager, UserClockedInNotification::class);
        Notification::assertSentTo($admin, UserClockedInNotification::class);
    }

    public function test_admin_receives_the_notification_on_the_database_channel_only(): void
    {
        Notification::fake();

        [$team, $manager] = $this->makeTeamWithManager();
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create();
        $team->users()->attach($employee, ['role' => 'employee']);

        $this->clockIn($employee, $team);

        Notification::assertSentTo(
            $admin,
            UserClockedInNotification::class,
            fn ($notification, array $channels) => $channels === ['database']
        );

        Notification::assertSentTo(
            $manager,
            UserClockedInNotification::class,
            fn ($notification, array $channels) => in_array('mail', $channels, true)
        );
    }

    public function test_manager_is_not_notified_of_their_own_clock_in(): void
    {
        Notification::fake();

        [$team, $manager] = $this->makeTeamWithManager();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->clockIn($manager, $team);

        Notification::assertNotSentTo($manager, UserClockedInNotification::class);
        Notification::assertSentTo($admin, UserClockedInNotification::class);
    }

    public function test_employees_are_notified_in_app_and_by_mail_when_their_manager_clocks_in(): void
    {
        Notification::fake();

        [$team, $manager] = $this->makeTeamWithManager();
        $employee = User::factory()->create();
        $team->users()->attach($employee, ['role' => 'employee']);

        $this->clockIn($manager, $team);

        Notification::assertSentTo(
            $employee,
            UserClockedInNotification::class,
            fn ($notification, array $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
        );
    }

    public function test_employees_are_not_notified_when_another_employee_clocks_in(): void
    {
        Notification::fake();

        [$team, $manager] = $this->makeTeamWithManager();
        $employee = User::factory()->create();
        $team->users()->attach($employee, ['role' => 'employee']);
        $coworker = User::factory()->create();
        $team->users()->attach($coworker, ['role' => 'employee']);

        $this->clockIn($employee, $team);

        Notification::assertNotSentTo($coworker, UserClockedInNotification::class);
    }

    public function test_admin_is_not_notified_of_their_own_clock_in(): void
    {
        Notification::fake();

        [$team, $manager] = $this->makeTeamWithManager();
        $admin = User::factory()->create(['is_admin' => true]);
        $team->users()->attach($admin, ['role' => 'employee']);

        $this->clockIn($admin, $team);

        Notification::assertNotSentTo($admin, UserClockedInNotification::class);
    }
}