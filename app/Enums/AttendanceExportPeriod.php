<?php

namespace App\Enums;

/**
 * The time spans an attendance CSV export can cover.
 */
enum AttendanceExportPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';
}
