<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Setup Roles & Permissions
        $this->call(RolePermissionSeeder::class);

        // Create the System Admin (In their own team)
        $admin = User::factory()->withPersonalTeam()->create([
            'name' => 'System Admin',
            'email' => 'admin@bel4o.dev',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        // Give the Admin the 'admin' role key in their personal team
        $admin->personalTeam()->users()->attach($admin, ['role' => 'admin']);

        // Create the second team: "MyFirstOffice"
        // We link it to the Admin as the owner
        $officeTeam = Team::forceCreate([
            'user_id' => $admin->id,
            'name' => 'MyFirstOffice',
            'personal_team' => false,
        ]);

        // Ensure Admin has the 'admin' role in this team too
        $officeTeam->users()->attach($admin, ['role' => 'admin']);
    }
}
