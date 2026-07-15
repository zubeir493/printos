<?php

namespace App\Filament\Resources\JobOrderTasks\Pages;

use App\Filament\Resources\JobOrderTasks\Actions\JobOrderTaskWorkflowActions;
use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Filament\Support\MaterialRequestActionForms;
use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\MaterialIssueService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewJobOrderTask extends ViewRecord
{
    protected static string $resource = JobOrderTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('cancel_task')
                    ->label('Cancel Task')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn () => PanelAccess::canManageJobOrderTasks())
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to cancel this task? This action cannot be undone.')
                    ->action(function ($record) {
                        $record->cancel();

                        Notification::make()
                            ->title('Task Cancelled')
                            ->body('The task has been cancelled successfully.')
                            ->danger()
                            ->send();
                    }),
                JobOrderTaskWorkflowActions::assignDesigner(),
                JobOrderTaskWorkflowActions::assignTypist(),
                JobOrderTaskWorkflowActions::sendToProduction(),
                Action::make('log_production')
                    ->label('Log Production')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('gray')
                    ->visible(fn ($record) => $record !== null
                        && $record->materialRequests()->where('issued_quantity', '>', 0)->exists()
                        && in_array(Filament::getCurrentPanel()?->getId(), ['production', 'operations', 'admin']))
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
                Action::make('request_materials')
                    ->label('Request Materials')
                    ->icon('heroicon-o-document-plus')
                    ->color('gray')
                    ->visible(fn () => Filament::getCurrentPanel()?->getId() === 'production')
                    ->modalWidth('lg')
                    ->form(fn (JobOrderTask $record): array => MaterialRequestActionForms::requestMaterialsForm($record))
                    ->action(function (array $data, $record) {
                        $created = MaterialRequestActionForms::createMaterialRequests($record, $data);

                        Notification::make()
                            ->title($created ? 'Materials Requested' : 'No materials requested')
                            ->success()
                            ->send();
                    }),
                Action::make('issue_materials')
                    ->label('Issue Materials')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('gray')
                    ->visible(fn ($record) => $record !== null
                        && $record->materialRequests()
                            ->whereColumn('issued_quantity', '<', 'requested_quantity')
                            ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                            ->exists()
                        && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations', 'warehouse']))
                    ->modalWidth('lg')
                    ->form(fn ($record) => [
                        MaterialRequestActionForms::warehouseSelect(),
                        MaterialRequestActionForms::issueItemsRepeater(),
                    ])
                    ->action(function ($record, $data) {
                        try {
                            $results = ['issued' => 0, 'pending_approval' => 0];
                            \DB::beginTransaction();
                            foreach ($data['items'] as $item) {
                                if (($item['quantity'] ?? 0) <= 0) {
                                    continue;
                                }
                                $mr = MaterialRequest::find($item['material_request_id']);
                                if (! $mr) {
                                    throw new \Exception('Material request not found.');
                                }
                                $result = app(MaterialIssueService::class)->issue($mr, (int) $data['warehouse_id'], (float) $item['quantity'], auth()->user());
                                $results[$result['status']]++;
                            }
                            \DB::commit();
                            Notification::make()
                                ->title(trim(collect([
                                    $results['issued'] ? "{$results['issued']} item(s) issued" : null,
                                    $results['pending_approval'] ? "{$results['pending_approval']} item(s) sent for approval" : null,
                                ])->filter()->implode(' | ')) ?: 'No materials processed')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \DB::rollBack();
                            Notification::make()
                                ->title('Error Issuing Materials')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                EditAction::make()
                    ->visible(fn () => PanelAccess::canManageJobOrderTasks())
                    ->color('gray'),
            ]),
        ];
    }
}
