<?php

namespace App\Filament\Resources\JobOrders\Actions;

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Support\MaterialRequestActionForms;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\InventoryItem;
use App\Models\MaterialRequest;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\InvoiceGeneratorService;
use App\Services\JobOrders\DuplicateJobOrder;
use App\Services\MaterialIssueService;
use App\States\JobOrder\Cancelled;
use App\States\JobOrder\Completed;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobOrderActions
{
    public static function make(): ActionGroup
    {
        return ActionGroup::make([
            self::edit(),
            self::reorder(),
            self::print(),
            self::activate(),
            self::complete(),
            self::cancel(),
            self::issueMaterials(),
            self::returnMaterials(),
            self::generatePurchaseOrder(),
            self::invoice(),
            self::pay(),
        ]);
    }

    public static function edit(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->url(fn ($record): string => JobOrderResource::getUrl('edit', ['record' => $record]))
            ->visible(fn ($record = null): bool => is_object($record)
                && PanelAccess::canManageJobOrders()
                && (string) $record->status === 'draft');
    }

    public static function reorder(): Action
    {
        return Action::make('reorder')
            ->label('Reorder')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->visible(fn ($record = null): bool => is_object($record)
                && PanelAccess::canManageJobOrders()
                && (string) $record->status !== 'draft')
            ->requiresConfirmation()
            ->modalHeading('Reorder this job order?')
            ->modalDescription('This creates a new draft job order with the same tasks and current dates.')
            ->action(function ($record) {
                $duplicate = app(DuplicateJobOrder::class)->handle($record);

                Notification::make()
                    ->title('Job order duplicated')
                    ->body("Created {$duplicate->job_order_number}.")
                    ->success()
                    ->send();

                return redirect(JobOrderResource::getUrl('edit', ['record' => $duplicate]));
            });
    }

    public static function print(): Action
    {
        return Action::make('print_job_order')
            ->label('Print Job Order')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->url(fn ($record): string => route('job-orders.print', $record))
            ->openUrlInNewTab();
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Start Job Order')
            ->icon('heroicon-o-rocket-launch')
            ->color('gray')
            ->visible(fn ($record = null): bool => is_object($record)
                && (string) $record->status === 'draft'
                && PanelAccess::canManageJobOrders())
            ->requiresConfirmation()
            ->modalHeading('Start this Job Order?')
            ->modalDescription('This marks the job order as active and signals that work has begun. Make sure all tasks and materials are set up.')
            ->action(function ($record): void {
                try {
                    $record->update(['status' => 'active']);

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
            });
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label('Mark as Completed')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->visible(fn ($record = null): bool => is_object($record)
                && (string) $record->status === 'active'
                && PanelAccess::canManageJobOrders())
            ->requiresConfirmation()
            ->modalHeading('Complete Job Order')
            ->modalDescription('Mark this job order as completed? Make sure all tasks and dispatches are done.')
            ->action(function ($record): void {
                $record->status->transitionTo(Completed::class);
                Notification::make()->title('Job order marked as completed')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel_job_order')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn ($record = null): bool => is_object($record)
                && (string) $record->status === 'active'
                && PanelAccess::canManageJobOrders())
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
            });
    }

    public static function invoice(): Action
    {
        return Action::make('invoice')
            ->label('Invoice')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->hidden(fn ($record = null): bool => ! is_object($record)
                || $record->invoices()->exists()
                || ! PanelAccess::canSeeMoneyValues()
                || $record->balance <= 0
                || ($record->production_mode ?? null) === 'make_to_stock')
            ->action(function ($record): void {
                try {
                    $invoiceService = app(InvoiceGeneratorService::class);
                    $result = $invoiceService->generateFromJobOrder($record);

                    Notification::make()
                        ->title('Invoice Generated')
                        ->body('Invoice '.$result['invoice_data']['invoice_number'].' created successfully.')
                        ->success()
                        ->actions([
                            Action::make('download')
                                ->label('Download')
                                ->color('gray')
                                ->url($invoiceService->getInvoicePath($result['filename']))
                                ->openUrlInNewTab(),
                        ])
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Invoice Action Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function pay(): Action
    {
        return Action::make('pay')
            ->label('Receive Payment')
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->visible(fn ($record = null): bool => is_object($record)
                && $record->balance > 0
                && PanelAccess::canAccessFinanceSection()
                && in_array((string) $record->status, ['active', 'completed'], true)
                && ($record->production_mode ?? null) !== 'make_to_stock')
            ->schema([
                Grid::make(2)->schema([
                    Select::make('method')
                        ->label('Payment method')
                        ->options([
                            'cash' => 'Cash',
                            'bank' => 'Bank Transfer',
                            'cheque' => 'Cheque',
                        ])
                        ->default('bank')
                        ->required()
                        ->live(),
                    Select::make('bank_id')
                        ->label('Bank Account')
                        ->options(fn (): array => Bank::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn (callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                        ->required(fn (callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                    DatePicker::make('payment_date')
                        ->label('Payment Date')
                        ->default(now())
                        ->required(),
                    TextInput::make('amount')
                        ->label('Total Applied')
                        ->required()
                        ->numeric()
                        ->suffix(fn (): string => Money::suffix())
                        ->default(fn ($record) => $record->balance)
                        ->helperText(fn ($record) => 'Balance: '.Money::format($record->balance)),
                    TextInput::make('withholding_amount')
                        ->label('Withholding')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(fn (callable $get): float => (float) ($get('amount') ?? 0))
                        ->suffix(fn (): string => Money::suffix()),
                    TextInput::make('reference')
                        ->label('Memo / Reference')
                        ->placeholder('Receipt number, cheque number, or short note')
                        ->maxLength(255),
                ]),
            ])
            ->action(function ($record, array $data) {
                try {
                    DB::transaction(function () use ($record, $data): void {
                        $lockedRecord = $record->newQuery()
                            ->lockForUpdate()
                            ->findOrFail($record->getKey());
                        $amount = (float) $data['amount'];
                        $withholdingAmount = (float) ($data['withholding_amount'] ?? 0);

                        if ($amount > $lockedRecord->balance) {
                            throw new \Exception('Cannot pay more than the remaining balance of '.Money::format($lockedRecord->balance).'.');
                        }

                        if ($withholdingAmount > $amount) {
                            throw new \Exception('Withholding cannot be greater than the settled payment amount.');
                        }

                        Payment::create([
                            'partner_id' => $lockedRecord->partner_id,
                            'payment_date' => $data['payment_date'],
                            'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
                            'amount' => $amount,
                            'withholding_amount' => $withholdingAmount,
                            'method' => $data['method'],
                            'bank_id' => $data['bank_id'] ?? null,
                            'reference' => $data['reference'] ?? 'Payment for '.$lockedRecord->job_order_number,
                            'payable_type' => get_class($lockedRecord),
                            'payable_id' => $lockedRecord->id,
                        ]);

                        $lockedRecord->updateQuietly([
                            'advance_paid' => true,
                            'advance_amount' => $lockedRecord->paid_amount + $amount,
                        ]);
                        $lockedRecord->refresh()->syncCompletionStatus();
                    });

                    Notification::make()
                        ->title('Payment Recorded')
                        ->body(Money::format($data['amount'])." applied to {$record->job_order_number}.")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Payment Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function issueMaterials(): Action
    {
        return Action::make('issue_materials')
            ->label('Issue Materials')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('gray')
            ->visible(
                fn ($record = null): bool => is_object($record) &&
                    PanelAccess::canAccessWarehouseSection() &&
                    ! in_array((string) $record->status, ['completed', 'cancelled'], true) &&
                    $record->materialRequests()
                        ->whereColumn('issued_quantity', '<', 'requested_quantity')
                        ->whereDoesntHave('pendingIssueApprovals', fn ($query) => $query->where('status', 'pending'))
                        ->whereHas('jobOrderTask', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
                        ->exists()
            )
            ->modalWidth('lg')
            ->form(fn ($record): array => [
                MaterialRequestActionForms::warehouseSelect(),
                MaterialRequestActionForms::issueItemsRepeater(
                    fn ($query) => $query->whereHas('jobOrderTask', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled'])),
                ),
            ])
            ->action(function ($record, array $data): void {
                try {
                    $results = ['issued' => 0, 'pending_approval' => 0];

                    foreach ($data['items'] as $item) {
                        if ($item['quantity'] <= 0) {
                            continue;
                        }

                        $materialRequest = MaterialRequest::findOrFail($item['material_request_id']);
                        $result = app(MaterialIssueService::class)->issue($materialRequest, (int) $data['warehouse_id'], (float) $item['quantity'], auth()->user());
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
            });
    }

    public static function returnMaterials(): Action
    {
        return Action::make('return_materials')
            ->label('Return Materials')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(
                fn ($record = null): bool => is_object($record) &&
                    PanelAccess::canAccessWarehouseSection() &&
                    (string) $record->status !== 'completed' &&
                    $record->materialRequests()
                        ->where('issued_quantity', '>', 0)
                        ->whereHas('jobOrderTask', fn ($q) => $q->where('status', '!=', 'completed'))
                        ->exists()
            )
            ->form(fn ($record): array => [
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
                    ->default(fn (): array => $record->materialRequests()
                        ->where('issued_quantity', '>', 0)
                        ->whereHas('jobOrderTask', fn ($q) => $q->where('status', '!=', 'completed'))
                        ->get()
                        ->map(function ($materialRequest) use ($record): array {
                            $originalWarehouse = StockMovement::where('type', 'consumption')
                                ->where('reference_id', $record->id)
                                ->where('inventory_item_id', $materialRequest->inventory_item_id)
                                ->orderByDesc('movement_date')
                                ->value('warehouse_id');

                            return [
                                'material_request_id' => $materialRequest->id,
                                'inventory_item_id' => $materialRequest->inventory_item_id,
                                'original_warehouse_id' => $originalWarehouse,
                                'quantity' => 0,
                            ];
                        })->toArray()),
            ])
            ->action(function ($record, array $data): void {
                try {
                    DB::transaction(function () use ($record, $data): void {
                        $inventoryService = app(InventoryService::class);
                        $defaultWarehouseId = Warehouse::where('is_default', true)->value('id');

                        foreach ($data['items'] as $item) {
                            if ($item['quantity'] <= 0) {
                                continue;
                            }

                            $materialRequest = MaterialRequest::findOrFail($item['material_request_id']);

                            if ($item['quantity'] > $materialRequest->issued_quantity) {
                                throw new \Exception("Cannot return more than what was issued for {$materialRequest->inventoryItem->name}.");
                            }

                            $inventoryService->receiveStock(
                                $materialRequest->inventory_item_id,
                                $item['original_warehouse_id'] ?? $defaultWarehouseId,
                                $item['quantity'],
                                $materialRequest->inventoryItem->price,
                                'material_return',
                                $record->id
                            );

                            $materialRequest->decrement('issued_quantity', $item['quantity']);
                        }
                    });

                    Notification::make()
                        ->title('Materials Returned Successfully')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error Returning Materials')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function generatePurchaseOrder(): Action
    {
        return Action::make('generate_po')
            ->label('Generate PO')
            ->icon('heroicon-o-shopping-cart')
            ->color('gray')
            ->visible(
                fn ($record = null): bool => is_object($record) &&
                    PanelAccess::canManagePurchaseOrders() &&
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
                    $purchaseOrder = DB::transaction(function () use ($record, $data): PurchaseOrder {
                        $missingMaterials = collect($record->materials_summary)->where('remaining', '>', 0);

                        if ($missingMaterials->isEmpty()) {
                            throw new \Exception('No missing materials found.');
                        }

                        $purchaseOrder = PurchaseOrder::create([
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

                            $purchaseOrder->purchaseOrderItems()->create([
                                'inventory_item_id' => $inventoryItem->id,
                                'quantity' => round($purchaseQty, 4),
                                'unit_price' => $unitPrice,
                                'total' => round($purchaseQty * $unitPrice, 2),
                                'status' => 'pending',
                            ]);
                        }

                        $purchaseOrder->recalculateTotals();

                        Notification::make()
                            ->title('Purchase Order Generated')
                            ->success()
                            ->send();

                        return $purchaseOrder;
                    });

                    return redirect(EditPurchaseOrder::getUrl(['record' => $purchaseOrder]));
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error Generating PO')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
