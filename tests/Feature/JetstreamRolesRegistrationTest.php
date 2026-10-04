<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Providers\JetstreamServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

/**
 * JetstreamServiceProvider registers the database roles on every request.
 *
 * It used to run a Schema::hasTable('roles') query first, every time, even
 * though the roles themselves came from the cache. Now a warm cache costs no
 * database query at all; the table is only checked when the cache is empty.
 */
class JetstreamRolesRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function bootProvider(): void
    {
        $this->app->register(JetstreamServiceProvider::class, force: true);
    }

    public function test_a_warm_cache_registers_roles_without_touching_the_database(): void
    {
        // The base TestCase already booted the provider once, warming the cache.
        $this->assertNotNull(Cache::get(Role::CACHE_KEY));

        DB::enableQueryLog();
        $this->bootProvider();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
        $this->assertNotNull(Jetstream::findRole('manager'));
    }

    public function test_a_cold_cache_reads_the_roles_from_the_database(): void
    {
        Role::forgetJetstreamCache();

        $this->bootProvider();

        $this->assertNotNull(Cache::get(Role::CACHE_KEY));
        $this->assertNotNull(Jetstream::findRole('manager'));
    }
}
