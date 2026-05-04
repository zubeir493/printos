<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use App\Models\JobOrderTask;
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
                                return \App\Models\JobOrder::where('production_mode', 'make_to_order')
                                    ->whereNotIn('status', ['completed', 'cancelled'])
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
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        Select::make('warehouse_id')
                            ->label('Dispatch From Warehouse')
                            ->options(\App\Models\Warehouse::pluck('name', 'id'))
                            ->default(fn () => \App\Models\Warehouse::where('is_default', true)->value('id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('warehouse_id', $state)),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                    ])->columnSpan(3),
                Section::make('Delivered Items')
                    ->schema(function (callable $get, ?\App\Models\Dispatch $record) {
                        $jobOrderId = $get('job_order_id');
                        
                        if (!$jobOrderId) {
                            return [];
                        }
                        
                        return collect(JobOrderTask::where('job_order_id', $jobOrderId)->get())
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
                                        
                                        // Debug info
                                        if (!$warehouseId) {
                                            return "Please select a warehouse";
                                        }
                                        
                                        $availableQty = 0;
                                        
                                        // For client jobs, look for WIP items with new SKU format
                                        if ($productionMode === 'make_to_order') {
                                            $itemSku = 'TASK-' . $task->id;
                                            $inventoryItem = \App\Models\InventoryItem::where('sku', $itemSku)->first();
                                            
                                            if ($inventoryItem) {
                                                $balance = \App\Models\InventoryBalance::where('warehouse_id', $warehouseId)
                                                    ->where('inventory_item_id', $inventoryItem->id)
                                                    ->first();
                                                $availableQty = $balance ? $balance->quantity_on_hand : 0;
                                            } else {
                                                return "WIP item not found (SKU: {$itemSku})";
                                            }
                                        } else {
                                            // For internal jobs, look for any finished good
                                            $inventoryItem = \App\Models\InventoryItem::where('type', 'finished_good')
                                                ->where('name', 'like', "%{$task->name}%")
                                                ->first();
                                            
                                            if ($inventoryItem) {
                                                $balance = \App\Models\InventoryBalance::where('warehouse_id', $warehouseId)
                                                    ->where('inventory_item_id', $inventoryItem->id)
                                                    ->first();
                                                $availableQty = $balance ? $balance->quantity_on_hand : 0;
                                            } else {
                                                return "Finished good not found";
                                            }
                                        }
                                        
                                        return "Available: {$availableQty} {$itemType}";
                                    });
                            })
                            ->values()
                            ->all();
                    })->columnSpan(2)
                    ->reactive()
            ])->columns(5);
    }
}
