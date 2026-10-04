<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Shift Length
    |--------------------------------------------------------------------------
    |
    | The longest a single shift may run, in hours. A shift clocked out later
    | than this is recorded at exactly this length, whether the clock-out came
    | from the dashboard timer, the API or the scheduled close-expired-workdays
    | command. The dashboard countdown and the work stats tiles use it too.
    |
    */

    'max_shift_hours' => (int) env('ATTENDANCE_MAX_SHIFT_HOURS', 8),

];
