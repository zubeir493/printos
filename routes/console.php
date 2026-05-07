<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily database backup at 2 AM
Schedule::command('backup:database')->dailyAt('02:00');

// Mark overdue invoices every morning
Schedule::command('invoices:update-overdue')->dailyAt('06:00')->withoutOverlapping();

// Nightly reconciliation safety net — keeps invoice balances in sync
Schedule::command('invoices:fix-balances')->dailyAt('03:00')->withoutOverlapping();
