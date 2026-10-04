<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The global admin flag grants every permission through Gate::before, so it
 * must never be set by mass assignment — a future `$user->update($request->all())`
 * would otherwise be a privilege escalation. It is set explicitly instead.
 */
class UserAdminFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_flag_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $user->update(['name' => 'Renamed', 'is_admin' => true]);

        $this->assertSame('Renamed', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_the_admin_seeder_still_creates_the_admin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertTrue(User::where('email', 'admin@bel4o.dev')->sole()->is_admin);
    }

    public function test_the_admin_seeder_restores_the_flag_on_an_existing_account(): void
    {
        User::factory()->create(['email' => 'admin@bel4o.dev', 'is_admin' => false]);

        $this->seed(AdminSeeder::class);

        $this->assertTrue(User::where('email', 'admin@bel4o.dev')->sole()->is_admin);
    }
}
