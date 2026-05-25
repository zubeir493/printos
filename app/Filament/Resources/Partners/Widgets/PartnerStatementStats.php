<?php

namespace App\Filament\Resources\Partners\Widgets;

use App\Models\Partner;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PartnerStatementStats extends StatsOverviewWidget
{
    public ?Partner $record = null;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        if (! $this->record instanceof Partner) {
            return [];
        }

        $summary = $this->record->statementSummary();
        $netIsPayable = $summary['net_balance'] < 0;

        return [
            Stat::make('Receivables Outstanding', Money::format($summary['receivable_total']))
                ->description('Open sales and job order balances')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color('primary'),

            Stat::make('Payables Outstanding', Money::format($summary['payable_total']))
                ->description('Open supplier purchase balances')
                ->descriptionIcon('heroicon-m-arrow-down-left')
                ->color('danger'),

            Stat::make('Payments Received', Money::format($summary['inbound_payments_total']))
                ->description('Posted customer receipts')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Net Position', Money::format(abs($summary['net_balance'])))
                ->description($netIsPayable ? 'Net payable to partner' : 'Net receivable from partner')
                ->descriptionIcon('heroicon-m-scale')
                ->color($netIsPayable ? 'danger' : 'success'),
        ];
    }
}
