<?php

namespace App\Exceptions;

use App\Enums\LeaveStatus;
use Illuminate\Support\Str;

/**
 * Someone else approved, denied or cancelled the request first.
 */
class LeaveRequestAlreadyDecided extends BusinessRuleException
{
    public function __construct(public readonly LeaveStatus $status)
    {
        parent::__construct(__('This leave request has already been :status.', ['status' => Str::lower($status->label())]));
    }
}
