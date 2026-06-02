<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'clock_in',
        'clock_out',
        'worked_minutes',
        'work_day'
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return is_null($this->clock_out);
    }

    /**
     * "8h 32m" from worked_minutes, or calculates on the fly if clock_out exists.
     */
    public function durationForHumans(): ?string
    {
        $mins = $this->worked_minutes;

        if (! $mins && $this->clock_out) {
            $mins = (int) $this->clock_in->diffInMinutes($this->clock_out);
        }

        if (! $mins) return null;

        $h = intdiv($mins, 60);
        $m = $mins % 60;

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    /**
     * Returns 'in', 'late', or 'out'.
     * Late = clocked in after 09:15.
     */
    public function status(int $lateHour = 9, int $lateMinute = 15): string
    {
        if ($this->isActive()) {
            $threshold = $this->clock_in->copy()->setTime($lateHour, $lateMinute);
            return $this->clock_in->gt($threshold) ? 'late' : 'in';
        }

        return 'out';
    }

    public function clockInFormatted(): string
    {
        return $this->clock_in->format('H:i');
    }

    public function clockOutFormatted(): ?string
    {
        return $this->clock_out?->format('H:i');
    }
}
