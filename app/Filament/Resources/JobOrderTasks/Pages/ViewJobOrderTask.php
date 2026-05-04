<?php

namespace App\Filament\Resources\JobOrderTasks\Pages;

use App\Models\User;
use App\UserRole;
use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Services\MaterialIssueService;
use Filament\Actions;
use Filament\Facades\Filament;
use App\Filament\Support\PanelAccess;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJobOrderTask extends ViewRecord
{
    protected static string $resource = JobOrderTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('cancel_task')
                ->label('Cancel Task')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn ($record) => !in_array($record->status, ['cancelled', 'completed'])
                    && PanelAccess::canManageJobOrderTasks())
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to cancel this task? This action cannot be undone.')
                ->action(function ($record) {
                    $record->cancel();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Task Cancelled')
                        ->body('The task has been cancelled successfully.')
                        ->danger()
                        ->send();
                }),
            \Filament\Actions\Action::make('assign_designer')
                ->label('Assign Designer')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->visible(fn ($record) => blank($record->designer_id) 
                    && !in_array($record->status, ['completed', 'cancelled'])
                    && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations']))
                ->form([
                    \Filament\Forms\Components\Select::make('designer_id')
                        ->label('Designer')
                        ->options(\App\Models\User::where('role', 'designer')->pluck('name', 'id'))
                        ->required(),
                ])
                ->action(function (array $data, $record) {
                    $record->update(['designer_id' => $data['designer_id']]);
                    
                    // Update status automatically
                    $record->updateStatus();

                    \Filament\Notifications\Notification::make()
                        ->title(($data['designer_id'] ?? null) ? 'Designer assigned' : 'Designer unassigned')
                        ->success()
                        ->send();
                }),
            \Filament\Actions\Action::make('log_production')
                ->label('Log Production')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('success')
                ->visible(fn ($record) => !in_array($record->status, ['completed', 'cancelled'])
                    && $record->materialRequests()->where('issued_quantity', '>', 0)->exists()
                    && Filament::getCurrentPanel()?->getId() === 'production')
                ->form(function ($record) {
                    $productionMode = $record->jobOrder->production_mode;
                    
                    return [
                        \Filament\Forms\Components\Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->options(\App\Models\Warehouse::pluck('name', 'id'))
                            ->default(fn () => \App\Models\Warehouse::where('is_default', true)->value('id'))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('quantity')
                            ->label('Produced Quantity')
                            ->numeric()
                            ->required()
                            ->default(fn ($record) => $record->quantity),
                        // For internal jobs, allow selecting existing finished goods
                        \Filament\Forms\Components\Select::make('existing_inventory_item_id')
                            ->label('Select Finished Good')
                            ->options(\App\Models\InventoryItem::where('type', 'finished_good')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn () => $productionMode === 'make_to_stock')
                            ->helperText('Select an existing finished good or leave blank to create new'),
                    ];
                })
                ->action(function (array $data, $record) {
                    try {
                        \DB::beginTransaction();
                        
                        $productionMode = $record->jobOrder->production_mode;
                        $item = null;
                        $itemTypeName = '';
                        
                        if ($productionMode === 'make_to_order') {
                            // Client Job - Create WIP item with improved naming
                            $clientName = $record->jobOrder->partner->name ?? 'Unknown Client';
                            $jobOrderType = $record->jobOrder->job_type ?? 'Unknown';
                            $itemSku = 'TASK-' . $record->id;
                            $itemType = 'wip';
                            
                            $item = \App\Models\InventoryItem::firstOrCreate(
                                ['sku' => $itemSku],
                                [
                                    'name' => "{$record->name} - {$jobOrderType} - {$clientName} ({$record->jobOrder->job_order_number})",
                                    'type' => $itemType,
                                    'unit' => 'pcs',
                                    'is_sellable' => false,
                                    'price' => 0,
                                ]
                            );
                            $itemTypeName = 'WIP';
                        } else {
                            // Internal Job - Use existing finished good or create new
                            if (!empty($data['existing_inventory_item_id'])) {
                                $item = \App\Models\InventoryItem::find($data['existing_inventory_item_id']);
                            } else {
                                // Create new finished good
                                $itemSku = 'FG-TASK-' . $record->id;
                                $itemType = 'finished_good';
                                
                                $item = \App\Models\InventoryItem::firstOrCreate(
                                    ['sku' => $itemSku],
                                    [
                                        'name' => "Finished - {$record->name} ({$record->jobOrder->job_order_number})",
                                        'type' => $itemType,
                                        'unit' => 'pcs',
                                        'is_sellable' => true,
                                        'price' => 0,
                                    ]
                                );
                            }
                            $itemTypeName = 'Finished Good';
                        }

                        if ($item) {
                            \App\Models\StockMovement::create([
                                'inventory_item_id' => $item->id,
                                'warehouse_id' => $data['warehouse_id'],
                                'type' => 'production_output',
                                'reference_type' => \App\Models\JobOrderTask::class,
                                'reference_id' => $record->id,
                                'quantity' => abs($data['quantity']),
                                'movement_date' => now(),
                            ]);
                        }
                        \DB::commit();

                        // Update task status automatically
                        $record->updateStatus();

                        \Filament\Notifications\Notification::make()
                            ->title('Production Logged Successfully')
                            ->body("Added {$data['quantity']} units to {$itemTypeName}: {$item->name}")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        \Filament\Notifications\Notification::make()
                            ->title('Error Logging Production')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('request_materials')
                ->label('Request Materials')
                ->icon('heroicon-o-document-plus')
                ->color('info')
                ->visible(fn ($record) => !in_array($record->status, ['completed', 'cancelled'])
                    && Filament::getCurrentPanel()?->getId() === 'production')
                ->form(fn ($record) => [
                    \Filament\Forms\Components\Repeater::make('items')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            \Filament\Forms\Components\Select::make('inventory_item_id')
                                ->label('Material')
                                ->options(\App\Models\InventoryItem::pluck('name', 'id'))
                                ->disabled()
                                ->dehydrated()
                                ->required(),
                            \Filament\Forms\Components\TextInput::make('requested_quantity')
                                ->label('Quantity to Request')
                                ->numeric()
                                ->required()
                                ->hint(fn ($get) => "Required: " . ($record->paper[$get('paper_index')]['required_quantity'] ?? 0)),
                            \Filament\Forms\Components\Hidden::make('paper_index'),
                        ])->columns(2)
                        ->default(fn () => collect($record->paper ?? [])->map(fn ($item, $index) => [
                            'inventory_item_id' => $item['inventory_item_id'],
                            'requested_quantity' => ($item['required_quantity'] ?? 0) + ($item['reserve_quantity'] ?? 0),
                            'paper_index' => $index,
                        ])->toArray())
                ])
                ->action(function (array $data, $record) {
                    foreach ($data['items'] as $item) {
                        if ($item['requested_quantity'] <= 0) continue;
                        
                        \App\Models\MaterialRequest::create([
                            'job_order_task_id' => $record->id,
                            'inventory_item_id' => $item['inventory_item_id'],
                            'requested_quantity' => $item['requested_quantity'],
                            'required_quantity' => $record->paper[$item['paper_index']]['required_quantity'] ?? 0,
                        ]);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Materials Requested')
                        ->success()
                        ->send();
                }),
            \Filament\Actions\Action::make('issue_materials')
                ->label('Issue Materials')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('warning')
                ->visible(fn ($record) => !in_array($record->status, ['completed', 'cancelled'])
                    && $record->materialRequests()
                        ->whereColumn('issued_quantity', '<', 'requested_quantity')
                        ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                        ->exists()
                    && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations', 'warehouse']))
                ->form(fn ($record) => [
                    \Filament\Forms\Components\Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(\App\Models\Warehouse::pluck('name', 'id'))
                        ->default(fn () => \App\Models\Warehouse::where('is_default', true)->value('id'))
                        ->required()
                        ->searchable()
                        ->live(),
                    \Filament\Forms\Components\Repeater::make('items')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            \Filament\Forms\Components\Hidden::make('material_request_id'),
                            \Filament\Forms\Components\Select::make('inventory_item_id')
                                ->label('Material')
                                ->options(\App\Models\InventoryItem::pluck('name', 'id'))
                                ->disabled()
                                ->dehydrated(),
                            \Filament\Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->label('Quantity to Issue')
                                ->hint(function ($get, $record) {
                                    $pending = $record->materialRequests->find($get('material_request_id'))?->requested_quantity - $record->materialRequests->find($get('material_request_id'))?->issued_quantity;
                                    $warehouseId = $get('../../warehouse_id');
                                    $itemId = $get('inventory_item_id');
                                    $stock = $warehouseId ? \App\Models\InventoryBalance::where('warehouse_id', $warehouseId)->where('inventory_item_id', $itemId)->value('quantity_on_hand') ?? 0 : 0;
                                    return "Pending: {$pending} | In Stock: {$stock}";
                                })
                                ->maxValue(function ($get, $record) {
                                    $pending = $record->materialRequests->find($get('material_request_id'))?->requested_quantity - $record->materialRequests->find($get('material_request_id'))?->issued_quantity;
                                    $warehouseId = $get('../../warehouse_id');
                                    $itemId = $get('inventory_item_id');
                                    $stock = $warehouseId ? \App\Models\InventoryBalance::where('warehouse_id', $warehouseId)->where('inventory_item_id', $itemId)->value('quantity_on_hand') ?? 0 : 0;
                                    return min($pending, $stock);
                                })
                                ->helperText(function ($get, $record) {
                                    $warehouseId = $get('../../warehouse_id');
                                    if (!$warehouseId) return "Please select a warehouse first.";
                                    return 'If this exceeds the required quantity, it will be queued for approval instead of issuing immediately.';
                                })
                        ])->columns(2)
                        ->default(fn () => $record->materialRequests()
                            ->whereColumn('issued_quantity', '<', 'requested_quantity')
                            ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                            ->get()
                            ->map(fn ($mr) => [
                                'material_request_id' => $mr->id,
                                'inventory_item_id' => $mr->inventory_item_id,
                                'quantity' => $mr->requested_quantity - $mr->issued_quantity,
                            ])->toArray()),
                ])
                ->action(function ($record, $data) {
                    try {
                        $results = ['issued' => 0, 'pending_approval' => 0];
                        \DB::beginTransaction();
                        foreach ($data['items'] as $item) {
                            if (($item['quantity'] ?? 0) <= 0) continue;
                            $mr = \App\Models\MaterialRequest::find($item['material_request_id']);
                            if (!$mr) {
                                throw new \Exception('Material request not found.');
                            }
                            $result = app(MaterialIssueService::class)->issue($mr, (int) $data['warehouse_id'], (float) $item['quantity'], auth()->user());
                            $results[$result['status']]++;
                        }
                        \DB::commit();
                        \Filament\Notifications\Notification::make()
                            ->title(trim(collect([
                                $results['issued'] ? "{$results['issued']} item(s) issued" : null,
                                $results['pending_approval'] ? "{$results['pending_approval']} item(s) sent for approval" : null,
                            ])->filter()->implode(' | ')) ?: 'No materials processed')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        \Filament\Notifications\Notification::make()
                            ->title('Error Issuing Materials')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            EditAction::make()
                ->visible(fn () => PanelAccess::canManageJobOrderTasks()),
        ];
    }
}
