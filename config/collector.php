<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default working hours
    |--------------------------------------------------------------------------
    |
    | System-wide fallback used by CollectorLocationController::store() to reject
    | location pings sent outside working hours, when a collector's own profile
    | (collector_profiles.duty_start_time / duty_end_time) has no override set.
    | "Jangan melakukan pelacakan di luar jam kerja atau tanpa izin yang sesuai."
    |
    */

    'default_working_hours' => [
        'start' => env('COLLECTOR_DUTY_START', '07:00'),
        'end' => env('COLLECTOR_DUTY_END', '18:00'),
    ],

];
