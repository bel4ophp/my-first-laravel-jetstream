<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    /**
     * Where JetstreamServiceProvider caches the roles it registers with
     * Jetstream. Anything that changes a role, a permission or the pivot
     * between them has to clear it, so the key lives in one place.
     */
    public const CACHE_KEY = 'jetstream_roles_db';

    protected $fillable = ['key', 'name'];

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Drop the registered-roles cache.
     *
     * Note this does NOT fire for pivot writes: attach/detach/sync on the
     * permissions relation bypasses model events, so those callers (such as
     * RolePermissionSeeder) must call this themselves.
     */
    public static function forgetJetstreamCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetJetstreamCache());
        static::deleted(fn () => self::forgetJetstreamCache());
    }
}
