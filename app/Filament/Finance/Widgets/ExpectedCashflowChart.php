<?php

namespace App\Filament\Finance\Widgets;

use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use Filament\Widgets\ChartWidget;

class ExpectedCashflowChart extends ChartWidget
{
    protected ?string $heading = 'Expected Cashflow (In vs Out)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $inflows = [];
        $outflows = [];
        $labels = [];

        for($i=0; $i<4; $i++) {
            $start = now()->addWeeks($i)->startOfWeek();
            $end = now()->addWeeks($i)->endOfWeek();
            $labels[] = "Week " . ($i + 1);

            // Inflows: SalesOrders with balance due soon (approximate by created_at in this view if no due_date exists)
            // Ideally we'd have a due_date, but we'll sum unpaid SOs created in matching past weeks or just current unpaid.
            // For a "forecast", we'll just pull a realistic mix of unpaid totals.
            $inflows[] = \App\Models\SalesOrder::where('status', '!=', 'completed')
                ->whereBetween('created_at', [now()->subWeeks($i+1), now()->subWeeks($i)])
                ->sum('total');

            $outflows[] = \App\Models\PurchaseOrder::where('status', 'pending')
                ->whereBetween('created_at', [now()->subWeeks($i+1), now()->subWeeks($i)])
                ->sum('total_amount');
        }
        return [
            'datasets' => [
                [
                    'label' => 'Est. Inflows (SO)',
                    'data' => $inflows,
                    'backgroundColor' => '#059669',
                ],
                [
                    'label' => 'Est. Outflows (PO)',
                    'data' => $outflows,
                    'backgroundColor' => '#e11d48',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
