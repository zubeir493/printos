<?php

namespace App\Filament\Resources\JobOrderTasks\Tables;

use App\Filament\Exports\JobOrderTaskExporter;
use App\Filament\Support\PanelAccess;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialIssueService;
use App\UserRole;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class JobOrderTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Task')
                    ->weight('bold')
                    ->description(fn ($record) => $record->jobOrder->job_order_number)
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'design' => 'info',
                        'production' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('jobOrder.submission_date')
                    ->label('Deadline')
                    ->date()
                    ->sortable()
                    ->since()
                    ->color(fn ($state) => $state && Carbon::parse($state)->isPast() ? 'danger' : null),

            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(JobOrderTaskExporter::class)
                    ->visible(fn () => in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations', 'finance'])),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'design' => 'Design',
                        'production' => 'Production',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('designer_id')
                    ->label('Designer')
                    ->options(fn () => User::query()
                        ->where('role', UserRole::Design->value)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('assigned')
                    ->label('Assignment')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('designer_id'),
                        false: fn ($query) => $query->whereNull('designer_id'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->defaultSort('jobOrder.submission_date', 'desc')
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn () => PanelAccess::canManageJobOrderTasks()),
                    Action::make('assign_designer')
                        ->label('Assign Designer')
                        ->icon('heroicon-o-user-plus')
                        ->color('info')
                        ->visible(fn ($record) => blank($record->designer_id)
                            && ! in_array($record->status, ['completed', 'cancelled'])
                            && ! in_array($record->jobOrder->status, ['completed', 'draft'])
                            && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations']))
                        ->form([
                            Select::make('designer_id')
                                ->label('Designer')
                                ->options(User::where('role', 'design')->pluck('name', 'id'))
                                ->required(),
                            Textarea::make('instructions')
                                ->label('Brief / Instructions')
                                ->placeholder('Describe what needs to be designed, any specific requirements, references, or deadlines...')
                                ->rows(4)
                                ->helperText('This will be included in the notification sent to the designer and saved on the task.'),
                        ])
                        ->action(function (array $data, $record) {
                            $record->update([
                                'designer_id' => $data['designer_id'],
                                'instructions' => $data['instructions'] ?? null,
                            ]);

                            $record->updateStatus();

                            Notification::make()
                                ->title('Designer assigned')
                                ->success()
                                ->send();
                        }),
                    Action::make('request_materials')
                        ->label('Request Materials')
                        ->icon('heroicon-o-document-plus')
                        ->color('info')
                        ->visible(fn ($record) => ! in_array($record->status, ['completed', 'cancelled'])
                            && Filament::getCurrentPanel()?->getId() === 'production')
                        ->form(fn ($record) => [
                            Repeater::make('items')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->schema([
                                    Select::make('inventory_item_id')
                                        ->label('Material')
                                        ->options(InventoryItem::pluck('name', 'id'))
                                        ->disabled()
                                        ->dehydrated()
                                        ->required(),
                                    TextInput::make('requested_quantity')
                                        ->label('Quantity to Request')
                                        ->numeric()
                                        ->required()
                                        ->hint(fn ($get) => 'Required: '.($record->paper[$get('paper_index')]['required_quantity'] ?? 0)),
                                    Hidden::make('paper_index'),
                                ])->columns(2)
                                ->default(fn () => collect($record->paper ?? [])->map(fn ($item, $index) => [
                                    'inventory_item_id' => $item['inventory_item_id'],
                                    'requested_quantity' => ($item['required_quantity'] ?? 0),
                                    'paper_index' => $index,
                                ])->toArray()),
                            TextInput::make('reason')
                                ->label('Reason')
                                ->required(),
                        ])
                        ->action(function (array $data, $record) {
                            foreach ($data['items'] as $item) {
                                if ($item['requested_quantity'] <= 0) {
                                    continue;
                                }

                                MaterialRequest::create([
                                    'job_order_task_id' => $record->id,
                                    'inventory_item_id' => $item['inventory_item_id'],
                                    'requested_quantity' => $item['requested_quantity'],
                                    'required_quantity' => $record->paper[$item['paper_index']]['required_quantity'] ?? 0,
                                    'reason' => $data['reason'],
                                ]);
                            }

                            Notification::make()
                                ->title('Materials Requested')
                                ->success()
                                ->send();
                        }),
                    Action::make('issue_materials')
                        ->label('Issue Materials')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('warning')
                        ->visible(fn ($record) => ! in_array($record->status, ['completed', 'cancelled'])
                            && $record->materialRequests()
                                ->whereColumn('issued_quantity', '<', 'requested_quantity')
                                ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                                ->exists()
                            && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations', 'warehouse']))
                        ->form(fn ($record) => [
                            Select::make('warehouse_id')
                                ->label('Warehouse')
                                ->options(Warehouse::pluck('name', 'id'))
                                ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                                ->required()
                                ->searchable()
                                ->live(),
                            Repeater::make('items')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->schema([
                                    Hidden::make('material_request_id'),
                                    Select::make('inventory_item_id')
                                        ->label('Material')
                                        ->options(InventoryItem::pluck('name', 'id'))
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('quantity')
                                        ->numeric()
                                        ->required()
                                        ->label('Quantity to Issue')
                                        ->hint(function ($get, $record) {
                                            $pending = $record->materialRequests->find($get('material_request_id'))?->requested_quantity - $record->materialRequests->find($get('material_request_id'))?->issued_quantity;
                                            $warehouseId = $get('../../warehouse_id');
                                            $itemId = $get('inventory_item_id');
                                            $stock = $warehouseId ? InventoryBalance::where('warehouse_id', $warehouseId)->where('inventory_item_id', $itemId)->value('quantity_on_hand') ?? 0 : 0;

                                            return "Pending: {$pending} | In Stock: {$stock}";
                                        })
                                        ->maxValue(function ($get, $record) {
                                            $pending = $record->materialRequests->find($get('material_request_id'))?->requested_quantity - $record->materialRequests->find($get('material_request_id'))?->issued_quantity;
                                            $warehouseId = $get('../../warehouse_id');
                                            $itemId = $get('inventory_item_id');
                                            $stock = $warehouseId ? InventoryBalance::where('warehouse_id', $warehouseId)->where('inventory_item_id', $itemId)->value('quantity_on_hand') ?? 0 : 0;

                                            return min($pending, $stock);
                                        })
                                        ->helperText(function ($get, $record) {
                                            $warehouseId = $get('../../warehouse_id');
                                            if (! $warehouseId) {
                                                return 'Please select a warehouse first.';
                                            }

                                            return 'If this exceeds the required quantity, it will be queued for approval instead of issuing immediately.';
                                        }),
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

                                foreach ($data['items'] as $item) {
                                    if ($item['quantity'] <= 0) {
                                        continue;
                                    }

                                    $mr = MaterialRequest::findOrFail($item['material_request_id']);
                                    $result = app(MaterialIssueService::class)->issue($mr, (int) $data['warehouse_id'], (float) $item['quantity'], auth()->user());
                                    $results[$result['status']]++;
                                }

                                Notification::make()
                                    ->title(trim(collect([
                                        $results['issued'] ? "{$results['issued']} item(s) issued" : null,
                                        $results['pending_approval'] ? "{$results['pending_approval']} item(s) sent for approval" : null,
                                    ])->filter()->implode(' | ')) ?: 'No materials processed')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Issuing Materials')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                    Action::make('log_production')
                        ->label('Log Production')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->visible(fn ($record) => ! in_array($record->status, ['completed', 'cancelled'])
                            && $record->materialRequests()->where('issued_quantity', '>', 0)->exists()
                            && Filament::getCurrentPanel()?->getId() === 'production')
                        ->form(function ($record) {
                            $productionMode = $record->jobOrder->production_mode;

                            return [
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->options(Warehouse::pluck('name', 'id'))
                                    ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Produced Quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(fn ($record) => $record->remaining_production_quantity),
                                // For internal jobs, allow selecting existing finished goods
                                Select::make('existing_inventory_item_id')
                                    ->label('Select Finished Good')
                                    ->options(InventoryItem::where('type', 'finished_good')->pluck('name', 'id'))
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
                                    $clientName = $record->jobOrder->partner?->name ?? 'Internal';
                                    $jobOrderType = $record->jobOrder->job_type ?? 'Unknown';
                                    $itemSku = 'TASK-'.$record->id;
                                    $itemType = 'wip';

                                    $item = InventoryItem::firstOrCreate(
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
                                    if (! empty($data['existing_inventory_item_id'])) {
                                        $item = InventoryItem::find($data['existing_inventory_item_id']);
                                    } else {
                                        // Create new finished good
                                        $itemSku = 'FG-TASK-'.$record->id;
                                        $itemType = 'finished_good';

                                        $item = InventoryItem::firstOrCreate(
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
                                    StockMovement::create([
                                        'inventory_item_id' => $item->id,
                                        'warehouse_id' => $data['warehouse_id'],
                                        'type' => 'production_output',
                                        'reference_type' => JobOrderTask::class,
                                        'reference_id' => $record->id,
                                        'quantity' => abs($data['quantity']),
                                        'movement_date' => now(),
                                    ]);
                                }
                                \DB::commit();

                                // Update task status automatically
                                $record->updateStatus();

                                Notification::make()
                                    ->title('Production Logged Successfully')
                                    ->body("Added {$data['quantity']} units to {$itemTypeName}: {$item->name}")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                \DB::rollBack();
                                Notification::make()
                                    ->title('Error Logging Production')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManageJobOrderTasks()),
                    ExportBulkAction::make()
                        ->exporter(JobOrderTaskExporter::class)
                        ->visible(fn () => PanelAccess::canManageJobOrderTasks()),
                ]),
            ]);
    }
}
