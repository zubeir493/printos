<?php

namespace App\Filament\Resources\JobOrders\Tables;

use App\Filament\Exports\JobOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Services\InvoiceGeneratorService;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class JobOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_order_number')
                    ->label('Job Order')
                    ->description(fn ($record) => $record->partner?->name ?? 'Internal Order')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'warning',
                        'active' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->description(fn ($record) => $record->jobOrderTasks()->where('status', 'completed')->count().' / '.$record->jobOrderTasks()->count().' tasks done.'),
                TextColumn::make('submission_date')
                    ->label('Submission Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'danger' : null)
                    ->description(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'Late' : null),
                TextColumn::make('total')
                    ->label('Payment Progress')
                    ->formatStateUsing(fn ($record) => number_format($record->paid_amount, 2).'/'.number_format($record->total, 2).' birr')
                    ->visible(fn () => PanelAccess::canSeeMoneyValues())
                    ->sortable(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(JobOrderExporter::class),
            ])
            ->filters([
                TernaryFilter::make('payment_status')
                    ->label('Payment Status')
                    ->placeholder('All')
                    ->trueLabel('Pending Payments')
                    ->falseLabel('Fully Paid')
                    ->queries(
                        true: fn ($query) => $query->pendingPayment(),
                        false: fn ($query) => $query->fullyPaid(),
                    ),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->preload()
                    ->searchable(),
                Filter::make('late_jobs')
                    ->label('Late Job Orders')
                    ->query(fn ($query) => $query->late())
                    ->toggle(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn () => PanelAccess::canManageJobOrders()),
            ])
            ->recordActions([
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->hidden(fn ($record) => $record->invoices()->exists() || ! PanelAccess::canSeeMoneyValues() || $record->balance <= 0)
                    ->action(function ($record) {
                        try {
                            $invoiceService = app(InvoiceGeneratorService::class);
                            $result = $invoiceService->generateFromJobOrder($record);

                            $actions = [
                                Action::make('download')
                                    ->label('Download')
                                    ->url($invoiceService->getInvoicePath($result['filename']))
                                    ->openUrlInNewTab(),
                            ];

                            // Only add email action if partner has email
                            if ($record->partner && $record->partner->email) {
                                $actions[] = Action::make('email')
                                    ->label('Email Invoice')
                                    ->icon('heroicon-o-envelope')
                                    ->action(function () use ($record, $result, $invoiceService) {
                                        $sent = $invoiceService->sendInvoiceEmail(
                                            $result,
                                            $record->partner->email
                                        );

                                        if ($sent) {
                                            // Update invoice record with email info
                                            $result['invoice']->update([
                                                'emailed_at' => now(),
                                                'email_recipient' => $record->partner->email,
                                            ]);

                                            Notification::make()
                                                ->title('Invoice Sent')
                                                ->body('Invoice emailed to '.$record->partner->email)
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Email Failed')
                                                ->body('Failed to send invoice. Please check email configuration.')
                                                ->danger()
                                                ->send();
                                        }
                                    });
                            }

                            Notification::make()
                                ->title('Invoice Generated')
                                ->body('Invoice '.$result['invoice_data']['invoice_number'].' created successfully.')
                                ->success()
                                ->actions($actions)
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Invoice Action Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('pay')
                    ->label('Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => 
                        $record->balance > 0 && 
                        PanelAccess::canAccessFinanceSection() &&
                        in_array($record->status, ['active', 'completed'])
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
                                // Find payment that contains this allocation
                                $payment = $existingAllocation->payment;
                            } else {
                                // Create new payment for this customer
                                $payment = \App\Models\Payment::create([
                                    'payment_number' => 'PAY-' . str_pad(\App\Models\Payment::max('id') + 1, 6, '0', STR_PAD_LEFT),
                                    'partner_id' => $record->partner_id,
                                    'payment_date' => now(),
                                    'direction' => 'incoming',
                                    'amount' => $record->balance,
                                    'method' => 'cash',
                                    'reference' => 'Auto-created for ' . $record->job_order_number,
                                ]);
                            }
                            
                            if (!$payment) {
                                throw new \Exception("Failed to find or create payment for {$record->job_order_number}.");
                            }
                            
                            // Check if payment has sufficient unallocated amount
                            $totalAllocated = $payment->paymentAllocations()->sum('allocated_amount');
                            $availableAmount = $payment->amount - $totalAllocated;
                            
                            // If this is a newly created payment, exclude allocation we're about to create from the calculation
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
                                ->body("{$amount} Birr allocated to {$record->job_order_number} from {$payment->payment_number}")
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
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManageJobOrders()),
                    ExportBulkAction::make()
                        ->exporter(JobOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManageJobOrders()),
                ]),
            ]);
    }
}
