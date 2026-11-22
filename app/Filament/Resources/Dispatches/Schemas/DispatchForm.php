<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use App\Models\Dispatch;
use App\Models\JobOrder;
use App\Services\DispatchStockService;
use App\Support\WarehouseAccess;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('job_order_id')->label('Job Order')
                ->default(fn (): ?int => JobOrder::query()->whereKey(request()->integer('job_order_id'))->where('production_mode', 'make_to_order')->whereIn('status', ['production', 'completed'])->value('id'))
                ->relationship('jobOrder', 'job_order_number', modifyQueryUsing: fn ($query) => $query
                    ->where('production_mode', 'make_to_order')->whereIn('status', ['production', 'completed'])->with('partner'))
                ->getOptionLabelFromRecordUsing(fn (JobOrder $record): string => $record->job_order_number.' — '.$record->name)
                ->searchable()->preload()->required()->live(),
            DatePicker::make('delivery_date')->default(today())->required(),
            Select::make('warehouse_id')
                ->relationship('warehouse', 'name', modifyQueryUsing: fn (Builder $query): Builder => WarehouseAccess::scope($query, 'warehouses.id'))
                ->required()->searchable()->preload()->live(),
            TextInput::make('quantity')->label('Quantity to dispatch')->integer()
                ->minValue(fn (string $operation): int => $operation === 'create' ? 1 : 0)->required()
                ->helperText(fn (Get $get, ?Dispatch $record): string => self::quantityHelp($get, $record)),
            Textarea::make('remarks')->columnSpanFull(),
        ])->columns(2);
    }

    private static function quantityHelp(Get $get, ?Dispatch $record): string
    {
        $stockTiming = 'Saving updates stock immediately. Mark the dispatch as delivered after the customer receives it.';
        $warehouseId = filter_var($get('warehouse_id'), FILTER_VALIDATE_INT);
        $jobOrderId = filter_var($get('job_order_id'), FILTER_VALIDATE_INT);
        if (! $warehouseId || ! $jobOrderId || ! WarehouseAccess::allows($warehouseId)) {
            return 'Select a job and warehouse to see the quantity available. '.$stockTiming;
        }

        $job = JobOrder::query()
            ->where('production_mode', 'make_to_order')->whereIn('status', ['production', 'completed'])
            ->with('outputInventoryItem')->find($jobOrderId);
        if (! $job?->outputInventoryItem) {
            return 'This job has no dispatchable output item. '.$stockTiming;
        }

        $currentQuantity = $record?->job_order_id === $job->id ? (float) $record->dispatchItems()->sum('quantity') : 0.0;
        $remaining = $job->remaining_quantity + $currentQuantity;
        $available = app(DispatchStockService::class)->availableQuantity($job->outputInventoryItem, $warehouseId);
        if ($record) {
            $available -= (float) $record->stockMovements()->where('warehouse_id', $warehouseId)
                ->where('inventory_item_id', $job->outputInventoryItem->id)->sum('quantity');
        }

        return sprintf(
            'Remaining produced: %s. In this warehouse: %s. Available to dispatch: %s. %s',
            Number::format($remaining),
            Number::format($available),
            Number::format(max(0, floor(min($remaining, $available)))),
            $stockTiming,
        );
    }
}
