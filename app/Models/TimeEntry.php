<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'clock_in',
        'clock_out',
        'worked_minutes',
        'work_day',
    ];

    /**
     * `date:Y-m-d` shapes how work_day is serialized, not how it is written: a
     * Carbon is sent to the database as "Y-m-d H:i:s" and the DATE column drops
     * the time. Assigning a "Y-m-d" string (today()->toDateString()) keeps the
     * write identical to what the onDay()/forMonth() scopes compare against.
     */
    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'work_day' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Entries within the given month.
     *
     * Expressed as a range rather than whereYear()/whereMonth() so the
     * (user_id, work_day) index stays usable; wrapping the column in a SQL
     * function forces a full scan.
     */
    #[Scope]
    protected function forMonth(Builder $query, int $year, int $month): void
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        $query->whereBetween('work_day', [
            $start->toDateString(),
            $start->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Entries within the given year. Range-based for the same reason as forMonth().
     */
    #[Scope]
    protected function forYear(Builder $query, int $year): void
    {
        $query->whereBetween('work_day', [
            Carbon::create($year, 1, 1)->toDateString(),
            Carbon::create($year, 12, 31)->toDateString(),
        ]);
    }

    /**
     * Entries for one work day. work_day is a DATE column, so an equality
     * comparison is both correct and index-usable; whereDate() is not.
     */
    #[Scope]
    protected function onDay(Builder $query, Carbon|string $day): void
    {
        $query->where('work_day', $day instanceof Carbon ? $day->toDateString() : $day);
    }

    /**
     * Clocked in and not yet clocked out.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNotNull('clock_in')->whereNull('clock_out');
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

        if (! $mins) {
            return null;
        }

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
