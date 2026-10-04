<?php

namespace App\Exceptions;

/**
 * A time entry's clock-out comes before its clock-in.
 */
class InvalidClockTimes extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('Clock out must be after clock in.'));
    }
}
