<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Payment;
use App\Services\Accounting\ReceivablesQuery;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FinancePanelStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $windowStart = now()->subDays(30)->startOfDay();

        $incoming = (float) Payment::query()
            ->where('direction', 'inbound')->whereNull('voided_at')
            ->whereDate('payment_date', '>=', $windowStart)
            ->whereDate('payment_date', '<=', today())->sum(DB::raw('amount - COALESCE(withholding_amount, 0)'));

        $outgoing = (float) Payment::query()
            ->where('direction', 'outbound')->whereNull('voided_at')
            ->whereDate('payment_date', '>=', $windowStart)
            ->whereDate('payment_date', '<=', today())->sum(DB::raw('amount - COALESCE(withholding_amount, 0)'));

        $receivables = app(ReceivablesQuery::class)->total();
        $unlinkedPayments = (float) Payment::query()
            ->whereNull('payable_type')
            ->whereNull('voided_at')
            ->sum('amount');

        return [
            Stat::make('Net Cash Flow', Money::abbreviate($incoming - $outgoing, precision: 2))
                ->description('Inbound less outbound, last 30 days')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color(($incoming - $outgoing) >= 0 ? 'success' : 'danger'),
            Stat::make('Outstanding Receivables', Money::abbreviate($receivables, precision: 2))
                ->description('Unpaid client jobs and sales orders')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),
            Stat::make('Unlinked Payments', Money::abbreviate($unlinkedPayments, precision: 2))
                ->description('Payments not linked to orders')
                ->descriptionIcon('heroicon-m-scale')
                ->color($unlinkedPayments > 0 ? 'warning' : 'success'),
        ];
    }
}
