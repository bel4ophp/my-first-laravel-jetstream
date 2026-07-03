<?php

namespace Tests;

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Laravel\Jetstream\Jetstream;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAndRegisterRoles();
    }

    /**
     * Seed the application's roles/permissions and register them with Jetstream.
     *
     * Tests boot in the console, where JetstreamServiceProvider skips its
     * DB-driven role registration, so we mirror it here from the seeded data.
     * This keeps test roles in lockstep with production (no permission drift)
     * and guarantees every role carries a description.
     */
    protected function seedAndRegisterRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->seed(RolePermissionSeeder::class);

        Role::with('permissions')->get()->each(function (Role $role) {
            Jetstream::role(
                $role->key,
                $role->name,
                $role->permissions->pluck('key')->all(),
            )->description('Access managed via database.');
        });
    }
}