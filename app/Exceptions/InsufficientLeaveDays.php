<?php

namespace App\Exceptions;

use App\Models\User;

/**
 * The shared annual/free-day pool can't cover the request — either when it is
 * submitted, or later when it is approved after the pool has shrunk.
 */
class InsufficientLeaveDays extends BusinessRuleException
{
    public static function toSubmit(int $remainingDays): self
    {
        return new self(__('Insufficient leave days. You have :days day(s) remaining.', ['days' => $remainingDays]));
    }

    public static function toApprove(User $employee): self
    {
        return new self(__('Cannot approve: :name no longer has enough days in the pool.', ['name' => $employee->name]));
    }
}
