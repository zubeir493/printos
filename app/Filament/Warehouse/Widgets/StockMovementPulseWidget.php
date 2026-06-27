<?php

namespace App\Filament\Warehouse\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\StockMovement;
use LaravelDaily\FilaWidgets\Data\SparklineTableRowData;
use LaravelDaily\FilaWidgets\Data\SparklineTableWidgetData;
use LaravelDaily\FilaWidgets\Widgets\SparklineTableWidget;

class StockMovementPulseWidget extends SparklineTableWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 4;

    protected ?string $widgetLabel = 'Stock Movement Pulse';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected function getData(): SparklineTableWidgetData
    {
        $periods = $this->comparisonPeriods();
        $inbound = StockMovement::query()->where('quantity', '>', 0);
        $outbound = StockMovement::query()->where('quantity', '<', 0);
        $transfers = StockMovement::query()->where('type', 'transfer');

        return SparklineTableWidgetData::fromRows(
            new SparklineTableRowData('Inbound', $this->sumDuring($inbound, $periods['current'], 'quantity', 'movement_date'), $this->sumDuring($inbound, $periods['previous'], 'quantity', 'movement_date'), $this->sparkline($inbound, 'SUM(quantity)', 'movement_date', 0), 'number', 0, color: 'success'),
            new SparklineTableRowData('Outbound', abs($this->sumDuring($outbound, $periods['current'], 'quantity', 'movement_date')), abs($this->sumDuring($outbound, $periods['previous'], 'quantity', 'movement_date')), $this->sparkline($outbound, 'ABS(SUM(quantity))', 'movement_date', 0), 'number', 0, color: 'warning'),
            new SparklineTableRowData('Transfers', $this->countDuring($transfers, $periods['current'], 'movement_date'), $this->countDuring($transfers, $periods['previous'], 'movement_date'), $this->sparkline($transfers, 'COUNT(*)', 'movement_date', 0), 'number', 0),
        );
    }
}
