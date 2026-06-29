<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Models\StockMovement;
use App\Support\DateTimeDisplay;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('inventoryItem.name')
                            ->label('Inventory item')
                            ->weight('bold'),
                        TextEntry::make('warehouse.name')
                            ->label('Warehouse'),
                        TextEntry::make('movement_date')
                            ->label('Moved at')
                            ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state)),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Str::headline($state))
                            ->color(fn (string $state): string => match ($state) {
                                'purchase', 'transfer_in', 'material_return', 'production_output' => 'success',
                                'transfer_out', 'consumption', 'sale' => 'danger',
                                'dispatch' => 'warning',
                                'adjustment' => 'primary',
                                default => 'gray',
                            }),
                        TextEntry::make('reference_display')
                            ->label('Source document')
                            ->state(fn (StockMovement $record): string => self::referenceDisplay($record))
                            ->placeholder('-'),
                        TextEntry::make('quantity')
                            ->state(fn (StockMovement $record): string => self::quantityDisplay($record))
                            ->color(fn (StockMovement $record): string => (float) $record->quantity >= 0 ? 'success' : 'danger')
                            ->weight('bold'),
                    ]),
                Section::make('Value')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('unit_cost')
                            ->label(fn (StockMovement $record): string => self::pricePerUnitLabel($record))
                            ->formatStateUsing(fn ($state) => $state === null ? null : Money::format($state))
                            ->placeholder('-'),
                        TextEntry::make('total_cost')
                            ->label('Total cost')
                            ->formatStateUsing(fn ($state) => $state === null ? null : Money::format($state))
                            ->placeholder('-'),
                    ]),
            ]);
    }

    private static function quantityDisplay(StockMovement $record): string
    {
        $quantity = self::decimalDisplay($record->getRawOriginal('quantity') ?? $record->quantity);
        $unit = $record->inventoryItem?->unit ?: 'unit';

        return "{$quantity} {$unit}";
    }

    private static function pricePerUnitLabel(StockMovement $record): string
    {
        $unit = $record->inventoryItem?->unit ?: 'unit';

        return 'Price per '.Str::headline($unit);
    }

    private static function decimalDisplay(float|int|string|null $value): string
    {
        $value = (string) ($value ?? '0');

        if (! str_contains($value, '.')) {
            return "{$value}.00";
        }

        [$whole, $decimal] = explode('.', $value, 2);

        return $whole.'.'.str_pad($decimal, 2, '0');
    }

    private static function referenceDisplay(StockMovement $record): string
    {
        $type = $record->reference_type;
        $id = $record->reference_id;

        if (! $type) {
            return '-';
        }

        $label = self::referenceLabel($type);

        if (! $id || ! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            return $id ? "{$label}, #{$id}" : $label;
        }

        $reference = $type::query()->find($id);

        if (! $reference) {
            return "{$label}, #{$id}";
        }

        return "{$label}, ".self::referenceIdentifier($reference);
    }

    private static function referenceLabel(string $type): string
    {
        return class_exists($type)
            ? Str::headline(class_basename($type))
            : Str::headline($type);
    }

    private static function referenceIdentifier(Model $reference): string
    {
        foreach (
            [
                'job_order_number',
                'transfer_number',
                'adjustment_number',
                'order_number',
                'po_number',
                'bid_number',
                'invoice_number',
                'receipt_number',
                'payment_number',
                'name',
                'title',
            ] as $attribute
        ) {
            $value = $reference->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return '#'.$reference->getKey();
    }
}
