<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* ---- Channel Manager ---- */
Schedule::command('cm:pull-bookings')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('cm:push-inventory --days=90')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20)
    ->onOneServer();

/* ---- Night audit ---- */
Schedule::command('night-audit:run')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer();

/* ---- SaaS billing reminders ---- */
Schedule::command('hotelesy:send-renewal-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping(60)
    ->onOneServer();

/* ---- Auto-mark no-shows hourly ---- */
Schedule::command('reservations:auto-no-show')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer();
