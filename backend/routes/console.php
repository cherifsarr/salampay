<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Treasury Management Schedule
|--------------------------------------------------------------------------
*/

// Sync provider balances every 15 minutes
Schedule::command('treasury:reconcile')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/treasury.log'));

// Auto-sweep excess funds at midnight
Schedule::command('treasury:sweep')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/treasury.log'));

// Auto-fund low accounts early morning
Schedule::command('treasury:fund')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/treasury.log'));

/*
|--------------------------------------------------------------------------
| Accounting Schedule
|--------------------------------------------------------------------------
*/

// Generate daily balance sheet snapshot at end of day
Schedule::command('accounting:snapshot')
    ->dailyAt('23:55')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/accounting.log'));

// Verify platform balance every hour
Schedule::command('accounting:balance')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/accounting.log'));

// Reset wallet limits at midnight
Schedule::command('accounting:limits reset')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/accounting.log'));

// Check upgrade eligibility weekly
Schedule::command('accounting:limits check-upgrades')
    ->weeklyOn(1, '02:00') // Monday at 2 AM
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/accounting.log'));
