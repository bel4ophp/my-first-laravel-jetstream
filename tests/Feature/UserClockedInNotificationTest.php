<?php

namespace Tests\Feature;

use App\Enums\ClockAction;
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

    // ── Mail body ─────────────────────────────────────────────────────────────

    /**
     * toMail() shipped as unedited scaffolding ("The introduction to the
     * notification." linking to url('/')) and was mailed to a manager on every
     * clock-in. Nothing asserted the body, so nothing caught it.
     */
    public function test_a_clock_out_says_clocked_out_and_when(): void
    {
        [$team, $manager] = $this->makeTeamWithManager();
        $employee = User::factory()->create(['current_team_id' => $team->id]);
        $entry = TimeEntry::factory()->forDay('2026-06-15')->create(['user_id' => $employee->id]);

        $mail = (new UserClockedInNotification($employee, $entry, ClockAction::ClockOut))->toMail($manager);

        $this->assertSame("{$employee->name} clocked out at {$entry->clockOutFormatted()}", $mail->subject);
    }

    /**
     * Rows already in the notifications table hold the plain string, so the
     * enum must keep storing exactly that.
     */
    public function test_the_stored_action_is_the_plain_string_older_rows_hold(): void
    {
        [$team, $manager] = $this->makeTeamWithManager();
        $employee = User::factory()->create(['current_team_id' => $team->id]);
        $entry = TimeEntry::factory()->active()->create(['user_id' => $employee->id]);

        $data = (new UserClockedInNotification($employee, $entry, ClockAction::ClockIn))->toDatabase($manager);

        $this->assertSame('clock_in', $data['action']);
    }

    public function test_the_clock_in_email_says_who_clocked_in_and_when(): void
    {
        [$team, $manager] = $this->makeTeamWithManager();

        $employee = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($employee, ['role' => 'employee']);

        $entry = TimeEntry::factory()->forDay('2026-06-15')->create(['user_id' => $employee->id]);

        $mail = (new UserClockedInNotification($employee, $entry, ClockAction::ClockIn))->toMail($manager);
        $rendered = (string) $mail->render();

        $this->assertStringContainsString($employee->name, $mail->subject);
        $this->assertStringContainsString($entry->clockInFormatted(), $mail->subject);
        $this->assertStringContainsString($manager->name, $rendered);
        $this->assertStringContainsString('Jun 15, 2026', $rendered);

        // Asserted on the message rather than the rendered HTML, which escapes
        // the ampersand in the query string.
        $this->assertSame(
            route('reports.attendance.index', ['year' => 2026, 'month' => 6]),
            $mail->actionUrl
        );
    }

    public function test_the_clock_in_email_carries_no_scaffolding_text(): void
    {
        [$team, $manager] = $this->makeTeamWithManager();

        $employee = User::factory()->create(['current_team_id' => $team->id]);
        $entry = TimeEntry::factory()->forDay('2026-06-15')->create(['user_id' => $employee->id]);

        $rendered = (string) (new UserClockedInNotification($employee, $entry, ClockAction::ClockIn))
            ->toMail($manager)
            ->render();

        $this->assertStringNotContainsString('The introduction to the notification', $rendered);
        $this->assertStringNotContainsString('Notification Action', $rendered);
        $this->assertStringNotContainsString('Thank you for using our application', $rendered);
    }
}
