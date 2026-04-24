<?php

namespace App\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Permission extends Model
{
    protected $fillable = ['key', 'name'];

    /**
     * The roles that belong to the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Clear cache when permissions are updated or detached.
     */
    protected static function booted()
    {
        static::saved(fn () => Cache::forget('jetstream_roles_db'));
        static::deleted(fn () => Cache::forget('jetstream_roles_db'));
    }
}
