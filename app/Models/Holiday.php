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
     * `date:Y-m-d` shapes serialization, not writes: a Carbon is sent as
     * "Y-m-d H:i:s" and the DATE column drops the time. Writers pass "Y-m-d"
     * strings so stored values match the `unique` lookups exactly.
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
