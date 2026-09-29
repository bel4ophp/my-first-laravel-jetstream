<?php

namespace Tests;

use App\Providers\JetstreamServiceProvider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

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
     * JetstreamServiceProvider boots before the test database has been migrated,
     * so it finds no roles table and registers nothing. Booting it a second time
     * against the seeded data puts tests on the same registration path as
     * production, rather than mirroring that logic here where it could drift.
     */
    protected function seedAndRegisterRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->seed(RolePermissionSeeder::class);

        $this->app->register(JetstreamServiceProvider::class, force: true);
    }
}
