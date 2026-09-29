<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    protected static function booted(): void
    {
        static::saved(fn () => Role::forgetJetstreamCache());
        static::deleted(fn () => Role::forgetJetstreamCache());
    }
}
