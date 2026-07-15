<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Support\MaterialRequestActionForms;
use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use App\Models\MaterialRequest;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\MaterialIssueService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditJobOrder extends EditRecord
{
    protected static string $resource = JobOrderResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->getRecord()->job_order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('issue_materials')
                    ->label('Issue Materials')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('gray')
                    ->visible(
                        fn ($record) => PanelAccess::canAccessWarehouseSection() &&
                            $record->materialRequests()
                                ->whereColumn('issued_quantity', '<', 'requested_quantity')
                                ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                                ->whereHas('jobOrderTask', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
                                ->exists()
                    )
                    ->modalWidth('lg')
                    ->form(fn ($record) => [
                        MaterialRequestActionForms::warehouseSelect(),
                        MaterialRequestActionForms::issueItemsRepeater(
                            fn ($query) => $query->whereHas('jobOrderTask', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled'])),
                        ),
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
                Action::make('return_materials')
                    ->label('Return Materials')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(
                        fn ($record) => PanelAccess::canAccessWarehouseSection() &&
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
                DeleteAction::make(),
            ]),
        ];
    }
}
