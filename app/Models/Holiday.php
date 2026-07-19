<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'date',
    ];

    /**
     * `date:Y-m-d` keeps writes as a plain date. A bare `date` cast writes
     * "Y-m-d H:i:s", which MySQL silently truncates but SQLite stores verbatim
     * — breaking `unique` lookups that compare against "Y-m-d".
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}