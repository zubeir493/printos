<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use App\Models\Dispatch;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\Warehouse;
use App\Services\DispatchStockService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Select::make('job_order_id')
                            ->relationship('jobOrder', 'id')
                            ->options(function () {
                                return JobOrder::where('production_mode', 'make_to_order')
                                    ->where('status', 'active')
                                    ->with('partner')
                                    ->get()
                                    ->map(function ($jobOrder) {
                                        $clientName = $jobOrder->partner?->name ?? 'Unknown Client';

                                        return [
                                            'id' => $jobOrder->id,
                                            'display_name' => "{$jobOrder->job_order_number} - {$clientName}",
                                        ];
                                    })
                                    ->pluck('display_name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->helperText('Only active client jobs are shown'),
                        DatePicker::make('delivery_date')
                            ->default(now())
                            ->required(),
                        Select::make('warehouse_id')
                            ->label('Dispatch From Warehouse')
                            ->options(Warehouse::pluck('name', 'id'))
                            ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('warehouse_id', $state)),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                    ])->columnSpan(3),
                Section::make('Delivered Items')
                    ->schema(function (callable $get, ?Dispatch $record) {
                        $jobOrderId = $get('job_order_id');

                        if (! $jobOrderId) {
                            return [];
                        }

                        return collect(JobOrderTask::query()
                            ->with('jobOrder')
                            ->where('job_order_id', $jobOrderId)
                            ->get())
                            ->map(function ($task) use ($record) {
                                $dispatchedQty = 0;
                                if ($record) {
                                    $item = $record->dispatchItems()->where('job_order_task_id', $task->id)->first();
                                    if ($item) {
                                        $dispatchedQty = $item->quantity;
                                    }
                                }

                                return TextInput::make("quantities.{$task->id}")
                                    ->label($task->name)
                                    ->numeric()
                                    ->default($dispatchedQty)
                                    ->formatStateUsing(fn ($state) => $state ?? $dispatchedQty)
                                    ->minValue(0)
                                    ->reactive()
                                    ->helperText(function (callable $get) use ($task) {
                                        $warehouseId = $get('warehouse_id');
                                        $productionMode = $task->jobOrder->production_mode;
                                        $itemType = $productionMode === 'make_to_order' ? 'wip' : 'finished_good';

                                        if (! $warehouseId) {
                                            return 'Please select a warehouse';
                                        }

                                        $availableQty = 0;

                                        // For client jobs, look for WIP items with new SKU format
                                        if ($productionMode === 'make_to_order') {
                                            $itemSku = 'TASK-'.$task->id;
                                            $inventoryItem = InventoryItem::where('sku', $itemSku)->first();

                                            if ($inventoryItem) {
                                                $balance = InventoryBalance::where('warehouse_id', $warehouseId)
                                                    ->where('inventory_item_id', $inventoryItem->id)
                                                    ->first();
                                                $availableQty = $balance ? $balance->quantity_on_hand : 0;
                                            } else {
                                                return "WIP item not found (SKU: {$itemSku})";
                                            }
                                        } else {
                                            // For internal jobs, look for any finished good
                                            $inventoryItem = InventoryItem::where('type', 'finished_good')
                                                ->where('name', 'like', "%{$task->name}%")
                                                ->first();

                                            if ($inventoryItem) {
                                                $balance = InventoryBalance::where('warehouse_id', $warehouseId)
                                                    ->where('inventory_item_id', $inventoryItem->id)
                                                    ->first();
                                                $availableQty = $balance ? $balance->quantity_on_hand : 0;
                                            } else {
                                                return 'Finished good not found';
                                            }
                                        }

                                        return "Available: {$availableQty} {$itemType}";
                                    });
                            })
                            ->values()
                            ->all();
                    })->columnSpan(2)
                    ->reactive(),
            ])->columns(5);
    }

    /**
     * @param  array<int|string, int|float|string|null>  $quantities
     */
    private static function firstDispatchIssue(int|string|null $jobOrderId, int|string|null $warehouseId, array $quantities, ?Dispatch $record = null): ?string
    {
        if (! $jobOrderId || ! $warehouseId || empty($quantities)) {
            return null;
        }

        $dispatchStock = app(DispatchStockService::class);
        $currentQuantities = $record
            ? $record->dispatchItems()->pluck('quantity', 'job_order_task_id')
            : collect();

        foreach ($quantities as $taskId => $quantity) {
            $issue = $dispatchStock->dispatchIssue(
                $taskId,
                $warehouseId,
                $quantity,
                $currentQuantities->get($taskId, 0),
            );

            if ($issue) {
                return $issue;
            }
        }

        return null;
    }
}
