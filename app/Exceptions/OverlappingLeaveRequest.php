<?php

namespace App\Exceptions;

/**
 * A pending or approved request already covers part of the requested range.
 */
class OverlappingLeaveRequest extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('You already have a leave request covering part of this range.'));
    }
}
