<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('clusters:activate-rate-schedules')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('billings:notify-penalty-changes')
    ->dailyAt('07:00')
    ->withoutOverlapping();

Schedule::command('supervisor:generate-notifications')
    ->hourly()
    ->withoutOverlapping();

// Jaring pengaman cache status akun penagihan (refresh per-event sudah berjalan lewat observer).
Schedule::command('collection:refresh-account-states')
    ->hourly()
    ->withoutOverlapping();
