<?php

namespace App\Filament\Exports;

use App\Models\InventoryBalance;
use App\Support\Money;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class InventoryBalanceExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = InventoryBalance::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('inventoryItem.name')
                ->label('Item'),
            ExportColumn::make('warehouse.name')
                ->label('Warehouse'),
            ExportColumn::make('quantity_on_hand')
                ->label('Quantity')
                ->formatStateUsing(function ($state, $record) {
                    $item = $record->inventoryItem;
                    if (! $item) {
                        return number_format($state);
                    }

                    if ($item->hasPurchaseUnit()) {
                        return number_format($item->toPurchaseUnits((float) $state), 2).' '.$item->purchase_unit;
                    }

                    return number_format($state).' '.$item->unit;
                }),
            ExportColumn::make('total_value')
                ->label('Total Value ('.Money::suffix().')')
                ->state(function (InventoryBalance $record): float {
                    $item = $record->inventoryItem;
                    if (! $item || in_array($item->type, ['tools', 'spare_parts', 'wip'])) {
                        return 0.0;
                    }

                    $baseUnitCost = $item->hasPurchaseUnit()
                        ? ((float) ($item->average_cost ?? 0) > 0
                            ? (float) $item->average_cost
                            : (float) ($item->price ?? 0) / (float) $item->conversion_factor)
                        : (float) ($item->average_cost > 0 ? $item->average_cost : ($item->price ?? 0));

                    return (float) $record->quantity_on_hand * $baseUnitCost;
                }),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your inventory balance export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
