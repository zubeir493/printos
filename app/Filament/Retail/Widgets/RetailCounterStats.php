<?php

namespace App\Filament\Retail\Widgets;

use App\Models\InventoryBalance;
use App\Models\SalesOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RetailCounterStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $cashSales = (float) SalesOrder::query()
            ->where('payment_mode', 'cash')
            ->whereDate('order_date', today())
            ->sum('total');

        $tickets = SalesOrder::query()
            ->whereDate('order_date', today())
            ->count();

        $sellableSkus = InventoryBalance::query()
            ->where('quantity_on_hand', '>', 0)
            ->whereHas('inventoryItem', fn ($query) => $query->where('is_sellable', true))
            ->count();

        return [
            Stat::make('Counter Sales Today', number_format($cashSales, 2))
                ->description('Cash sales booked today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($cashSales > 0 ? 'success' : 'gray')
                ->chart($this->cashSalesTrend()),
            Stat::make('Tickets Today', $tickets)
                ->description('Retail orders processed')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('info'),
            Stat::make('Sellable Stock Lines', $sellableSkus)
                ->description('Available stock for counter sale')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($sellableSkus > 0 ? 'success' : 'danger'),
        ];
    }

    private function cashSalesTrend(): array
    {
        return collect(range(6, 0))
            ->map(fn (int $daysAgo) => (float) SalesOrder::query()
                ->where('payment_mode', 'cash')
                ->whereDate('order_date', today()->subDays($daysAgo))
                ->sum('total'))
            ->all();
    }
}
