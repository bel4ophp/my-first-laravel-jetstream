<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:close-expired-workdays')
    ->everyFiveMinutes() // or ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('CloseExpiredWorkdays scheduler failed');
    });
