<?php

namespace App\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case FreeDay = 'free_day';
    case Unpaid = 'unpaid';
    case Sick = 'sick';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual Leave',
            self::FreeDay => 'Free Day',
            self::Unpaid => 'Unpaid Leave',
            self::Sick => 'Sick Leave',
        };
    }

    public function deductsFromPool(): bool
    {
        return match ($this) {
            self::Annual, self::FreeDay => true,
            default => false,
        };
    }
}