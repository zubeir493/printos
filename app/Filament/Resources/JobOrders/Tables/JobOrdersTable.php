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
                        ->label('Receive Payment')
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
                                    ->visible(fn (callable $get) => $get('method') === 'bank')
                                    ->required(fn (callable $get) => $get('method') === 'bank'),
                                DatePicker::make('payment_date')
                                    ->label('Payment Date')
                                    ->default(now())
                                    ->required(),
                                TextInput::make('amount')
                                    ->label('Total Applied')
                                    ->required()
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->default(fn ($record) => $record->balance)
                                    ->helperText(fn ($record) => 'Balance: '.Money::format($record->balance)),
                                TextInput::make('withholding_amount')
                                    ->label('Withholding')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(fn (callable $get): float => (float) ($get('amount') ?? 0))
                                    ->suffix('Birr'),
                                TextInput::make('reference')
                                    ->label('Memo / Reference')
                                    ->placeholder('Receipt number, cheque number, or short note')
                                    ->maxLength(255),
                            ]),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                DB::beginTransaction();

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

                                DB::commit();

                                Notification::make()
                                    ->title('Payment Recorded')
                                    ->body(Money::format($amount)." applied to {$lockedRecord->job_order_number}.")
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
                    ExportBulkAction::make()
                        ->exporter(JobOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManageJobOrders()),
                ]),
            ]);
    }
}
