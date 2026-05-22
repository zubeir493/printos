<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancePanelStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $windowStart = now()->subDays(30)->startOfDay();

        $incoming = (float) Payment::query()
            ->where('direction', 'inbound')
            ->whereDate('payment_date', '>=', $windowStart)
            ->sum('amount');

        $outgoing = (float) Payment::query()
            ->where('direction', 'outbound')
            ->whereDate('payment_date', '>=', $windowStart)
            ->sum('amount');

        $receivables = (float) SalesInvoice::query()->sum('total_amount');
        $paidInvoices = (float) Payment::query()
            ->where('payable_type', SalesInvoice::class)
            ->whereNull('voided_at')
            ->sum('amount');
        $unlinkedPayments = (float) Payment::query()
            ->whereNull('payable_type')
            ->whereNull('voided_at')
            ->sum('amount');

        return [
            Stat::make('Net Cash Flow', Money::abbreviate($incoming - $outgoing, precision: 2))
                ->description('Inbound less outbound, last 30 days')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color(($incoming - $outgoing) >= 0 ? 'success' : 'danger'),
            Stat::make('Outstanding Receivables', Money::abbreviate(max(0, $receivables - $paidInvoices), precision: 2))
                ->description('Sales invoices less direct payments')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),
            Stat::make('Unlinked Payments', Money::abbreviate($unlinkedPayments, precision: 2))
                ->description('Payments not linked to orders')
                ->descriptionIcon('heroicon-m-scale')
                ->color($unlinkedPayments > 0 ? 'warning' : 'success'),
        ];
    }
}
