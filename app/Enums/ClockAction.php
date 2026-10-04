<?php

namespace App\Enums;

/**
 * What a time-tracking notification reports. The values are stored in the
 * notifications table, so they must not change.
 */
enum ClockAction: string
{
    case ClockIn = 'clock_in';
    case ClockOut = 'clock_out';
}
