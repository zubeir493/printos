<?php

namespace App\Filament\Resources\JobOrders\Tables;

use App\Enums\PaymentTransactionType;
use App\Filament\Exports\JobOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Payment;
use App\Services\InvoiceGeneratorService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

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
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->description(fn ($record) => $record->completed_job_order_tasks_count.' / '.$record->job_order_tasks_count.' tasks done.'),
                TextColumn::make('submission_date')
                    ->label('Submission Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'danger' : null)
                    ->description(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'Late' : null),
                TextColumn::make('total')
                    ->label('Payment Progress')
                    ->weight('bold')
                    ->formatStateUsing(fn ($record) => Money::format($record->paid_amount).'/'.Money::format($record->total))
                    ->description(fn ($record) => $record->balance > 0
                        ? 'Balance: '.Money::format($record->balance)
                        : 'Paid in full')
                    ->color(fn ($record): string => match (true) {
                        $record->balance <= 0 => 'success',
                        $record->paid_amount > 0 => 'warning',
                        default => 'danger',
                    })
                    ->weight(fn ($record): FontWeight => $record->balance <= 0
                        ? FontWeight::Bold
                        : FontWeight::SemiBold)
                    ->visible(fn ($record) => is_object($record)
                        && PanelAccess::canSeeMoneyValues()
                        && ($record->production_mode ?? null) !== 'make_to_stock')
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
            ->defaultSort('submission_date', 'desc')
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn () => PanelAccess::canManageJobOrders()),
                ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('print_job_order')
                        ->label('Print Job Order')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->url(fn ($record): string => route('job-orders.print', $record))
                        ->openUrlInNewTab(),
                    Action::make('invoice')
                        ->label('Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->hidden(fn ($record) => ! is_object($record)
                            || $record->invoices()->exists()
                            || ! PanelAccess::canSeeMoneyValues()
                            || $record->balance <= 0
                            || ($record->production_mode ?? null) === 'make_to_stock')
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
                        ->label('Recieve Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn ($record) => is_object($record)
                            && $record->balance > 0
                            && PanelAccess::canAccessFinanceSection()
                            && in_array($record->status, ['active', 'completed'])
                            && ($record->production_mode ?? null) !== 'make_to_stock'
                        )
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('method')
                                    ->label('Paid Via')
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
                                    ->options(Bank::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (callable $get) => $get('method') === 'bank')
                                    ->required(fn (callable $get) => $get('method') === 'bank'),
                                DatePicker::make('payment_date')
                                    ->label('Payment Date')
                                    ->default(now())
                                    ->required(),
                                TextInput::make('amount')
                                    ->label('Payment Amount')
                                    ->required()
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->default(fn ($record) => $record->balance)
                                    ->helperText(fn ($record) => 'Balance: '.Money::format($record->balance)),
                                TextInput::make('reference')
                                    ->label('Memo / Reference')
                                    ->placeholder('Receipt number, cheque number, or short note')
                                    ->maxLength(255),
                            ]),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                DB::beginTransaction();

                                $amount = (float) $data['amount'];

                                if ($amount > $record->balance) {
                                    throw new \Exception('Cannot pay more than the remaining balance of '.Money::format($record->balance).'.');
                                }

                                Payment::create([
                                    'partner_id' => $record->partner_id,
                                    'payment_date' => $data['payment_date'],
                                    'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
                                    'amount' => $amount,
                                    'method' => $data['method'],
                                    'bank_id' => $data['bank_id'] ?? null,
                                    'reference' => $data['reference'] ?? 'Payment for '.$record->job_order_number,
                                    'payable_type' => get_class($record),
                                    'payable_id' => $record->id,
                                ]);

                                $record->updateQuietly([
                                    'advance_paid' => true,
                                    'advance_amount' => $record->paid_amount + $amount,
                                ]);
                                $record->refresh()->syncCompletionStatus();

                                DB::commit();

                                Notification::make()
                                    ->title('Payment Recorded')
                                    ->body(Money::format($amount)." received for {$record->job_order_number}.")
                                    ->success()
                                    ->send();

                            } catch (\Exception $e) {
                                DB::rollBack();

                                Notification::make()
                                    ->title('Payment Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
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
