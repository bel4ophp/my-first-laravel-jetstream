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
     * `date:Y-m-d` shapes serialization, not writes: a Carbon is sent as
     * "Y-m-d H:i:s" and the DATE column drops the time. Writers pass "Y-m-d"
     * strings so stored values match the range comparisons (e.g. the
     * `start_date <= :date` overlap check) exactly.
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
