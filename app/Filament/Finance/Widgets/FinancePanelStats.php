<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SalesInvoice;
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
        $allocated = (float) PaymentAllocation::query()
            ->where('allocatable_type', SalesInvoice::class)
            ->sum('allocated_amount');
        $unallocatedFunds = max(0, (float) Payment::query()->sum('amount') - (float) PaymentAllocation::query()->sum('allocated_amount'));

        return [
            Stat::make('Net Cash Flow', number_format($incoming - $outgoing, 2))
                ->description('Inbound less outbound, last 30 days')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color(($incoming - $outgoing) >= 0 ? 'success' : 'danger'),
            Stat::make('Outstanding Receivables', number_format(max(0, $receivables - $allocated), 2))
                ->description('Sales invoices less allocations')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),
            Stat::make('Unallocated Funds', number_format($unallocatedFunds, 2))
                ->description('Payments not linked to orders')
                ->descriptionIcon('heroicon-m-scale')
                ->color($unallocatedFunds > 0 ? 'warning' : 'success'),
        ];
    }
}
