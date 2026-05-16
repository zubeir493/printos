<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverduePaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $overdueInvoices = Invoice::overdue()
            ->with('partner')
            ->get();

        $totalOverdue = $overdueInvoices->sum('balance_due');
        $overdueCount = $overdueInvoices->count();

        // Get aging breakdown
        $aging = [
            '1-30' => $overdueInvoices->filter(fn ($inv) => $inv->due_date->diffInDays(now()) <= 30)->count(),
            '31-60' => $overdueInvoices->filter(fn ($inv) => $inv->due_date->diffInDays(now()) > 30 && $inv->due_date->diffInDays(now()) <= 60)->count(),
            '61-90' => $overdueInvoices->filter(fn ($inv) => $inv->due_date->diffInDays(now()) > 60 && $inv->due_date->diffInDays(now()) <= 90)->count(),
            '90+' => $overdueInvoices->filter(fn ($inv) => $inv->due_date->diffInDays(now()) > 90)->count(),
        ];

        return [
            Stat::make('Overdue Invoices', $overdueCount)
                ->description('Total overdue invoices')
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Overdue Amount', $totalOverdue)
                ->description('Total amount overdue')
                ->color('danger')
                ->icon('heroicon-o-currency-dollar')
                ->formatStateUsing(fn ($state) => Money::abbreviate($state, precision: 2)),

            Stat::make('1-30 Days', $aging['1-30'])
                ->description('Invoices overdue 1-30 days')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('31-60 Days', $aging['31-60'])
                ->description('Invoices overdue 31-60 days')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('60+ Days', $aging['61-90'] + $aging['90+'])
                ->description('Invoices overdue 60+ days')
                ->color('danger')
                ->icon('heroicon-o-exclamation-circle'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}
