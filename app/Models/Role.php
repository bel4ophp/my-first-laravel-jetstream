<?php

namespace App\Models;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    protected $fillable = ['key', 'name'];

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Optimized boot method to clear Jetstream cache when roles change.
     */
    protected static function booted()
    {
        static::saved(fn () => Cache::forget('jetstream_roles_db'));
        static::deleted(fn () => Cache::forget('jetstream_roles_db'));
    }
}
