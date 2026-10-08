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

    /*
    |--------------------------------------------------------------------------
    | Collection account
    |--------------------------------------------------------------------------
    |
    | Parameter perhitungan status & prioritas akun penagihan (CollectionAccountService,
    | CollectionPriorityService). Disimpan di config, bukan di kode/UI, supaya bisa disetel
    | per lingkungan tanpa deploy ulang frontend - web & Android selalu memakai angka yang sama.
    |
    */

    // Tagihan yang jatuh tempo dalam N hari ke depan berstatus "due_soon".
    'due_soon_days' => (int) env('COLLECTOR_DUE_SOON_DAYS', 7),

    // Outstanding yang dianggap "besar" (skor outstanding penuh & pemicu eskalasi).
    'large_outstanding_threshold' => (float) env('COLLECTOR_LARGE_OUTSTANDING', 5000000),

    // Jendela waktu untuk menghitung kontak/visit gagal dan PTP ingkar.
    'lookback_days' => [
        'failed_contact' => 30,
        'failed_visit' => 30,
        'broken_ptp' => 90,
    ],

    'priority' => [
        // Bobot (total 100). Setiap komponen dinormalisasi 0..1 terhadap batas di 'caps'.
        'weights' => [
            'aging' => 35,
            'outstanding' => 25,
            'broken_ptp' => 20,
            'failed_contact' => 10,
            'failed_visit' => 10,
        ],
        'caps' => [
            'aging_days' => 180,
            'broken_ptp' => 3,
            'failed_contact' => 5,
            'failed_visit' => 3,
        ],
        // Skor minimum untuk tiap level; di bawah 'medium' = normal.
        'levels' => [
            'critical' => 70,
            'high' => 45,
            'medium' => 20,
        ],
        // Akun yang sedang disengketakan tidak dinaikkan melebihi level ini.
        'disputed_max_level' => 'medium',
    ],

];
