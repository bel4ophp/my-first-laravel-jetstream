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
     * Runs once the application exists but before any trait — RefreshDatabase
     * included — touches the database. A check in setUp() would come too late:
     * the wipe happens inside parent::setUp().
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $this->ensureTestingDatabase();

        return parent::setUpTraits();
    }

    /**
     * RefreshDatabase wipes whatever database the suite points at. If the
     * phpunit.xml override is ever lost — a cached config, an env var set in
     * the container — the suite must stop rather than wipe the real data.
     */
    protected function ensureTestingDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_testing')) {
            $this->fail("Refusing to run against \"{$database}\": the test database name must end in \"_testing\".");
        }
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
