<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Support\PanelAccess;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\MaterialRequest;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\MaterialIssueService;
use App\States\JobOrder\Cancelled;
use App\States\JobOrder\Completed;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class ViewJobOrder extends ViewRecord
{
    protected static string $resource = JobOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->job_order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Start Job Order')
                ->icon('heroicon-o-rocket-launch')
                ->color(Color::Indigo)
                ->visible(fn ($record) => (string) $record->status === 'draft' && PanelAccess::canManageJobOrders())
                ->requiresConfirmation()
                ->modalHeading('Start this Job Order?')
                ->modalDescription('This marks the job order as active and signals that work has begun. Make sure all tasks and materials are set up.')
                ->action(function ($record) {
                    try {
                        $record->update(['status' => 'active']);
                        $this->record->refresh();

                        Notification::make()
                            ->title('Job order is now active')
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Job order cannot be started')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->persistent()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Job order could not be started')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('complete')
                ->label('Mark as Completed')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => (string) $record->status === 'active' && PanelAccess::canManageJobOrders())
                ->requiresConfirmation()
                ->modalHeading('Complete Job Order')
                ->modalDescription('Mark this job order as completed? Make sure all tasks and dispatches are done.')
                ->action(function ($record) {
                    $record->status->transitionTo(Completed::class);
                    Notification::make()->title('Job order marked as completed')->success()->send();
                }),

            Action::make('cancel_job_order')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record) => (string) $record->status === 'active' && PanelAccess::canManageJobOrders())
                ->form([
                    Textarea::make('cancel_reason')
                        ->label('Reason for cancellation')
                        ->placeholder('Why is this job order being cancelled?')
                        ->required()
                        ->rows(3),
                ])
                ->modalHeading('Cancel Job Order')
                ->action(function ($record, array $data) {
                    $record->update(['remarks' => trim(($record->remarks ? $record->remarks."\n\n" : '').'Cancelled: '.$data['cancel_reason'])]);
                    $record->status->transitionTo(Cancelled::class);
                    Notification::make()->title('Job order cancelled')->danger()->send();
                }),
            Action::make('issue_materials')
                ->label('Issue Materials')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('warning')
                ->visible(fn ($record) => PanelAccess::canAccessWarehouseSection() &&
                    ! in_array($record->status, ['completed', 'cancelled']) &&
                    $record->materialRequests()
                        ->whereColumn('issued_quantity', '<', 'requested_quantity')
                        ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                        ->whereHas('jobOrderTask', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
                        ->exists()
                )
                ->form(fn ($record) => [
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
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
                                ->options(fn (): array => InventoryItem::query()->orderBy('name')->pluck('name', 'id')->all())
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
                                ->helperText('If this exceeds the required quantity, it will be queued for approval instead of issuing immediately.'),
                        ])->columns(2)
                        ->default(fn () => $record->materialRequests()
                            ->whereColumn('issued_quantity', '<', 'requested_quantity')
                            ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                            ->whereHas('jobOrderTask', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
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
            Action::make('return_materials')
                ->label('Return Materials')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn ($record) => PanelAccess::canAccessWarehouseSection() &&
                    $record->status !== 'completed' &&
                    $record->materialRequests()
                        ->where('issued_quantity', '>', 0)
                        ->whereHas('jobOrderTask', fn ($q) => $q->where('status', '!=', 'completed'))
                        ->exists()
                )
                ->form(fn ($record) => [
                    Repeater::make('items')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            Hidden::make('material_request_id'),
                            Hidden::make('original_warehouse_id'),
                            Select::make('inventory_item_id')
                                ->label('Material')
                                ->options(fn (): array => InventoryItem::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->label('Quantity to Return')
                                ->hint(fn ($get) => 'Issued: '.$record->materialRequests->find($get('material_request_id'))?->issued_quantity)
                                ->maxValue(fn ($get) => $record->materialRequests->find($get('material_request_id'))?->issued_quantity),
                        ])->columns(2)
                        ->default(fn () => $record->materialRequests()
                            ->where('issued_quantity', '>', 0)
                            ->whereHas('jobOrderTask', fn ($q) => $q->where('status', '!=', 'completed'))
                            ->get()
                            ->map(function ($mr) use ($record) {
                                // Find the original warehouse this item was consumed from
                                $originalWarehouse = StockMovement::where('type', 'consumption')
                                    ->where('reference_id', $record->id)
                                    ->where('inventory_item_id', $mr->inventory_item_id)
                                    ->orderByDesc('movement_date')
                                    ->value('warehouse_id');

                                return [
                                    'material_request_id' => $mr->id,
                                    'inventory_item_id' => $mr->inventory_item_id,
                                    'original_warehouse_id' => $originalWarehouse,
                                    'quantity' => 0,
                                ];
                            })->toArray()),
                ])
                ->action(function ($record, $data) {
                    try {
                        $inventoryService = app(InventoryService::class);
                        $defaultWarehouseId = Warehouse::where('is_default', true)->value('id');
                        \DB::beginTransaction();

                        foreach ($data['items'] as $item) {
                            if ($item['quantity'] <= 0) {
                                continue;
                            }

                            $mr = MaterialRequest::find($item['material_request_id']);

                            if ($item['quantity'] > $mr->issued_quantity) {
                                throw new \Exception("Cannot return more than what was issued for {$mr->inventoryItem->name}.");
                            }

                            // Return to original warehouse if known, else default
                            $returnWarehouseId = $item['original_warehouse_id'] ?? $defaultWarehouseId;

                            $inventoryService->receiveStock(
                                $mr->inventory_item_id,
                                $returnWarehouseId,
                                $item['quantity'],
                                $mr->inventoryItem->price,
                                'material_return',
                                $record->id
                            );
                            $mr->decrement('issued_quantity', $item['quantity']);
                        }
                        \DB::commit();

                        Notification::make()
                            ->title('Materials Returned Successfully')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        Notification::make()
                            ->title('Error Returning Materials')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Actions\EditAction::make()
                ->visible(fn () => PanelAccess::canManageJobOrders())
                ->color(Color::Indigo),
            Action::make('generate_po')
                ->label('Generate PO')
                ->icon('heroicon-o-shopping-cart')
                ->color(Color::Indigo)
                ->visible(fn ($record) => PanelAccess::canManagePurchaseOrders() &&
                    (string) $record->status === 'active' &&
                    collect($record->materials_summary)->where('remaining', '>', 0)->isNotEmpty()
                )
                ->form([
                    Select::make('partner_id')
                        ->label('Supplier')
                        ->options(fn (): array => Partner::query()
                            ->where('is_supplier', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->requiresConfirmation()
                ->action(function ($record, array $data) {
                    try {
                        \DB::beginTransaction();

                        $missingMaterials = collect($record->materials_summary)->where('remaining', '>', 0);

                        if ($missingMaterials->isEmpty()) {
                            throw new \Exception('No missing materials found.');
                        }

                        $po = PurchaseOrder::create([
                            'partner_id' => $data['partner_id'],
                            'order_date' => now(),
                            'status' => 'draft',
                        ]);

                        foreach ($missingMaterials as $material) {
                            $inventoryItem = InventoryItem::where('name', $material['material_name'])->first();
                            if (! $inventoryItem) {
                                continue;
                            }

                            $purchaseQty = $material['remaining_purchase_qty'] ?? $inventoryItem->toPurchaseUnits($material['remaining']);
                            $unitPrice = $material['price_per_purchase_unit'] ?? $inventoryItem->pricePerPurchaseUnit();

                            $po->purchaseOrderItems()->create([
                                'inventory_item_id' => $inventoryItem->id,
                                'quantity' => round($purchaseQty, 4),
                                'unit_price' => $unitPrice,
                                'total' => round($purchaseQty * $unitPrice, 2),
                                'status' => 'pending',
                            ]);
                        }

                        $po->recalculateTotals();

                        \DB::commit();

                        Notification::make()
                            ->title('Purchase Order Generated')
                            ->success()
                            ->send();

                        $this->redirect(EditPurchaseOrder::getUrl(['record' => $po]));
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        Notification::make()
                            ->title('Error Generating PO')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
