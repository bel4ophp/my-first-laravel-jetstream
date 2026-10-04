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
            ]
        );

        // Set explicitly: is_admin and email_verified_at aren't mass assignable,
        // and this also restores the flag on re-runs against an existing record.
        $admin->forceFill([
            'is_admin' => true,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }
}
