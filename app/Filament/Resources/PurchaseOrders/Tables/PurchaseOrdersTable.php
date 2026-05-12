<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn ($record) => $record->partner?->name),
                TextColumn::make('order_date')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->description(fn ($record) => $record->purchaseOrderItems()->count().' items'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'info',
                        'received' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    }),
                TextColumn::make('payment_progress')
                    ->label('Payment Progress')
                    ->getStateUsing(function ($record) {
                        $total = $record->total ?? 0;
                        $paid = $record->paid_amount ?? 0;
                        if ($total == 0) return 'N/A';
                        $percentage = round(($paid / $total) * 100, 1);
                        return "{$percentage}% ({$paid}/{$total} ETB)";
                    })
                    ->description(function ($record) {
                        $balance = $record->balance ?? 0;
                        return $balance > 0 ? "Balance: {$balance} ETB" : 'Paid in full';
                    })
                    ->color(function ($record) {
                        $total = $record->total ?? 0;
                        $paid = $record->paid_amount ?? 0;
                        if ($total == 0) return 'gray';
                        $percentage = ($paid / $total) * 100;
                        if ($percentage >= 100) return 'success';
                        if ($percentage >= 50) return 'warning';
                        return 'danger';
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('partner_id')
                    ->label('Supplier')
                    ->options(Partner::where('is_supplier', true)->pluck('name', 'id')->toArray()),
                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn ($query) => $query->where('balance', '>', 0))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => 
                        $record->balance > 0 && 
                        PanelAccess::canAccessFinanceSection() &&
                        in_array($record->status, ['approved', 'received'])
                    )
                    ->form([
                        TextInput::make('allocated_amount')
                            ->label('Amount to Allocate')
                            ->required()
                            ->numeric()
                            ->suffix('Birr')
                            ->default(fn ($record) => $record->balance)
                            ->helperText(fn ($record) => "Balance: {$record->balance} Birr"),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            DB::beginTransaction();
                            
                            $amount = (float) $data['allocated_amount'];
                            
                            // Check if order has existing allocations
                            $existingAllocation = \App\Models\PaymentAllocation::where('allocatable_type', get_class($record))
                                ->where('allocatable_id', $record->id)
                                ->first();
                            
                            if ($existingAllocation) {
                                // Find the payment that contains this allocation
                                $payment = $existingAllocation->payment;
                            } else {
                                // Create new payment for this supplier
                                $payment = \App\Models\Payment::create([
                                    'payment_number' => 'PAY-' . str_pad(\App\Models\Payment::max('id') + 1, 6, '0', STR_PAD_LEFT),
                                    'partner_id' => $record->partner_id,
                                    'payment_date' => now(),
                                    'direction' => 'outgoing',
                                    'amount' => $record->balance,
                                    'method' => 'cash',
                                    'reference' => 'Auto-created for ' . $record->po_number,
                                ]);
                            }
                            
                            if (!$payment) {
                                throw new \Exception("Failed to find or create payment for {$record->po_number}.");
                            }
                            
                            // Check if payment has sufficient unallocated amount
                            $totalAllocated = $payment->paymentAllocations()->sum('allocated_amount');
                            $availableAmount = $payment->amount - $totalAllocated;
                            
                            // If this is a newly created payment, exclude the allocation we're about to create from the calculation
                            if (!isset($existingAllocation)) {
                                $availableAmount += $amount;
                            }
                            
                            if ($amount > $availableAmount) {
                                throw new \Exception("Only {$availableAmount} Birr available from this payment.");
                            }
                            
                            if ($amount > $record->balance) {
                                throw new \Exception("Cannot allocate more than balance of {$record->balance} Birr.");
                            }
                            
                            // Create payment allocation
                            $payment->paymentAllocations()->create([
                                'allocatable_id' => $record->id,
                                'allocatable_type' => get_class($record),
                                'allocated_amount' => $amount,
                            ]);
                            
                            DB::commit();
                            
                            Notification::make()
                                ->title('Payment Allocated')
                                ->body("{$amount} Birr allocated to {$record->po_number} from {$payment->payment_number}")
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            DB::rollBack();
                            
                            Notification::make()
                                ->title('Payment Allocation Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(PurchaseOrderExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                ]),
            ]);
    }
}
