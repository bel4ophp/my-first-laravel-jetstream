<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'calculated_days',
        'status',
        'approved_by',
        'notes',
        'cancelled_at',
    ];

    /**
     * `date:Y-m-d` keeps writes as plain dates. A bare `date` cast writes
     * "Y-m-d H:i:s", which MySQL truncates but SQLite stores verbatim —
     * breaking range comparisons (e.g. a request starting exactly on a
     * holiday date would fail a `start_date <= :date` overlap check).
     */
    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    public function isCancellableBy(User $user): bool
    {
        return $this->isPending() && $this->user_id === $user->id;
    }
}