<?php

namespace App\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case FreeDay = 'free_day';
    case Unpaid = 'unpaid';
    case Sick = 'sick';

    /**
     * The types a user may actually submit.
     *
     * Sick leave is defined but deferred — it needs the doctor-report flow
     * before it can be offered. Keeping the exclusion here rather than in the
     * form means fixtures and validation can't disagree about what is
     * submittable.
     *
     * @return array<int, self>
     */
    public static function submittable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type) => $type !== self::Sick,
        ));
    }

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