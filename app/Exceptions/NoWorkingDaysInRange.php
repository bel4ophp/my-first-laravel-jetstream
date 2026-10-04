<?php

namespace App\Exceptions;

/**
 * The requested range holds only weekends and holidays.
 */
class NoWorkingDaysInRange extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('The selected range contains no working days (only weekends/holidays).'));
    }
}
