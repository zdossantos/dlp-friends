<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('data-exports:cleanup')->hourly()->withoutOverlapping();
Schedule::command('accounts:dispatch-due-purges')->hourly()->withoutOverlapping();
