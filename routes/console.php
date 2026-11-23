<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:run')->dailyAt('00:10');
Schedule::command('subscriptions:expire')->everyTenMinutes()->withoutOverlapping();
Schedule::command('radius:ingest-accounting')->everyMinute();
Schedule::command('network:sync-router-status')->everyMinute()->withoutOverlapping();
Schedule::command('automation:run')->everyTenMinutes()->withoutOverlapping();
Schedule::command('platform-billing:run')->monthlyOn(1, '00:30')->withoutOverlapping();
