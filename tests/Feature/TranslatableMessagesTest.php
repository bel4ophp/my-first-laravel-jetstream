<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Livewire\LeaveApprovals;
use App\Livewire\LeaveRequestForm;
use App\Livewire\LeaveSettings;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The app's own messages go through the translator like Jetstream's do, so a
 * lang/sr.json can translate them. A throwaway JSON file stands in for one.
 */
class TranslatableMessagesTest extends TestCase
{
    use RefreshDatabase;

    private string $langPath;

    private Team $team;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-10');
        Notification::fake();

        $this->langPath = storage_path('framework/testing/lang');
        File::ensureDirectoryExists($this->langPath);
        File::put($this->langPath.'/sr.json', json_encode([
            'Leave request approved.' => 'Zahtev za odsustvo je odobren.',
            'You already have a leave request covering part of this range.' => 'Već imate zahtev koji pokriva deo ovog perioda.',
            'Available days reset for :count member(s).' => 'Dani su resetovani za :count člana.',
            'Annual Leave' => 'Godišnji odmor',
            'Approved' => 'Odobren',
        ]));
        app('translator')->addJsonPath($this->langPath);
        app()->setLocale('sr');

        $this->team = User::factory()->withPersonalTeam()->create()->currentTeam;
        $this->manager = $this->memberOf('manager');
        $this->employee = $this->memberOf('employee');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function memberOf(string $role): User
    {
        $user = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_a_flash_message_is_translated(): void
    {
        LeaveBalance::factory()->fresh()->create(['user_id' => $this->employee->id, 'year' => 2026]);
        $request = LeaveRequest::factory()->create([
            'user_id' => $this->employee->id,
            'type' => LeaveType::Annual,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'calculated_days' => 2,
            'status' => LeaveStatus::Pending,
        ]);

        Livewire::actingAs($this->manager)->test(LeaveApprovals::class)
            ->call('approve', $request->id)
            ->assertSee('Zahtev za odsustvo je odobren.');
    }

    public function test_a_service_error_is_translated(): void
    {
        LeaveBalance::factory()->fresh()->create(['user_id' => $this->employee->id, 'year' => 2026]);
        $submit = fn () => Livewire::actingAs($this->employee)->test(LeaveRequestForm::class)
            ->set('type', 'annual')
            ->set('startDate', '2026-06-15')
            ->set('endDate', '2026-06-16')
            ->call('submit');

        $submit();

        $submit()->assertHasErrors(['startDate' => 'Već imate zahtev koji pokriva deo ovog perioda.']);
    }

    public function test_a_message_with_a_count_is_translated_with_the_count_filled_in(): void
    {
        Livewire::actingAs($this->manager)->test(LeaveSettings::class)
            ->call('resetBalances')
            ->assertSee('Dani su resetovani za 2 člana.');
    }

    public function test_enum_labels_are_translated(): void
    {
        $this->assertSame('Godišnji odmor', LeaveType::Annual->label());
        $this->assertSame('Odobren', LeaveStatus::Approved->label());
    }
}
