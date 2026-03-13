<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('khatma:send-wird-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('khatma:send-periodic-reports weekly')
    ->weeklyOn(5, '20:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('khatma:send-periodic-reports monthly')
    ->monthlyOn(1, '09:00')
    ->withoutOverlapping()
    ->onOneServer();
