<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalPayments = Payment::count();
        $totalAmount = Payment::sum('amount');
        $paymentsToday = Payment::whereDate('payment_date', today())->count();
        $amountToday = Payment::whereDate('payment_date', today())->sum('amount');

        // Sample chart data
        $paymentsChart = [5, 8, 6, 10, 7, 9, $paymentsToday];
        $amountChart = [1000, 1200, 800, 1500, 1100, 1300, $amountToday];

        return [
            Stat::make('Total Payments', $totalPayments)
                ->description('All time payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
            Stat::make('Total Amount', Money::abbreviate($totalAmount, precision: 2))
                ->description('Cumulative payment value')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
            Stat::make('Payments Today', $paymentsToday)
                ->description('Transactions today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart($paymentsChart),
            Stat::make('Amount Today', Money::abbreviate($amountToday, precision: 2))
                ->description('Value processed today')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success')
                ->chart($amountChart),
        ];
    }
}
