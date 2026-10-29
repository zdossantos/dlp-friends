<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('accounts:dispatch-due-purges')->hourly()->withoutOverlapping();
Schedule::command('partners:purge-expired-records')
    ->dailyAt('03:30')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('visits:purge-expired')
    ->dailyAt('03:45')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('notifications:dispatch-weekly-recaps')
    ->weeklyOn(0, '15:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('trips:purge-expired')
    ->dailyAt('04:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('feedback:purge-expired')
    ->dailyAt('04:15')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onOneServer();
