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
