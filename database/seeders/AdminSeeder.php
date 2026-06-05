<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@bel4o.dev'],
            [
                'name' => 'System Admin',
                'password' => 'password',
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Ensure is_admin is set on re-runs where the record already existed
        if (! $admin->is_admin) {
            $admin->update(['is_admin' => true]);
        }
    }
}