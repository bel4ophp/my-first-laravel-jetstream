<?php

namespace App\Services;

use App\Enums\LeaveType;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    /**
     * The default size of the shared annual/free-day pool.
     */
    public const DEFAULT_POOL_DAYS = 20;

    /**
     * Get (or lazily create) the user's balance row for the given year.
     */
    public function currentBalance(User $user, ?int $year = null): LeaveBalance
    {
        $year ??= now()->year;

        return $user->leaveBalances()->firstOrCreate(
            ['year' => $year],
            ['total_days' => self::DEFAULT_POOL_DAYS, 'used_days' => 0],
        );
    }

    /**
     * Remaining pool days for the user in the given year.
     */
    public function remainingDays(User $user, ?int $year = null): int
    {
        return $this->currentBalance($user, $year)->remainingDays();
    }

    /**
     * Whether the requested days can be covered. Types that don't draw from
     * the pool (unpaid, sick) always pass.
     */
    public function hasSufficientDays(User $user, LeaveType $type, int $days, ?int $year = null): bool
    {
        if (! $type->deductsFromPool()) {
            return true;
        }

        return $this->remainingDays($user, $year) >= $days;
    }

    /**
     * Deduct days from the pool (used when a pool-drawing request is approved).
     */
    public function deduct(User $user, int $days, ?int $year = null): void
    {
        $balance = $this->currentBalance($user, $year);
        $balance->increment('used_days', $days);
    }

    /**
     * Return days to the pool (used when an approved request is reversed).
     */
    public function restore(User $user, int $days, ?int $year = null): void
    {
        $balance = $this->currentBalance($user, $year);
        $balance->decrement('used_days', min($days, $balance->used_days));
    }

    /**
     * Reset the pool (used_days -> 0) for the given users, creating a fresh
     * balance for anyone who doesn't have one yet.
     *
     * @param  Collection<int, int>  $userIds
     * @return int  the number of users whose pool was reset
     */
    public function resetUsedDays(Collection $userIds, ?int $year = null): int
    {
        $year ??= now()->year;

        return DB::transaction(function () use ($userIds, $year) {
            $existing = LeaveBalance::whereIn('user_id', $userIds)
                ->where('year', $year)
                ->pluck('user_id');

            $userIds->diff($existing)->each(fn (int $userId) => LeaveBalance::create([
                'user_id' => $userId,
                'year' => $year,
                'total_days' => self::DEFAULT_POOL_DAYS,
                'used_days' => 0,
            ]));

            LeaveBalance::whereIn('user_id', $userIds)
                ->where('year', $year)
                ->update(['used_days' => 0]);

            return $userIds->count();
        });
    }
}