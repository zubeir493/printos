<?php

use App\Models\AccountingIntegration;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily database backup at 2 AM
Schedule::command('backup:database')->dailyAt('02:00');

// Mark overdue invoices every morning
Schedule::command('invoices:update-overdue')->dailyAt('06:00')->withoutOverlapping();

// Notify about late job orders every morning
Schedule::command('job-orders:notify-late')->dailyAt('06:05')->withoutOverlapping();

// Nightly reconciliation safety net — keeps invoice balances in sync
Schedule::command('invoices:fix-balances')->dailyAt('03:00')->withoutOverlapping();

Schedule::command('payroll:generate-monthly-drafts')->monthlyOn(25, '06:15')->withoutOverlapping()->onOneServer();

if (Schema::hasTable('accounting_integrations')) {
    AccountingIntegration::query()
        ->where('enabled', true)
        ->whereIn('provider', AccountingIntegration::exportableProviders())
        ->get()
        ->each(function (AccountingIntegration $integration): void {
            Schedule::command("accounting:generate-exports --integration={$integration->id}")
                ->dailyAt(substr($integration->daily_cutoff, 0, 5))
                ->timezone($integration->timezone)
                ->name("accounting-export:{$integration->id}")
                ->withoutOverlapping()
                ->onOneServer();
        });
}
